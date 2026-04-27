<?php

namespace App\Controller;

use App\Entity\Attendance;
use App\Entity\Fixture;
use App\Entity\Player;
use App\Entity\Team;
use App\Entity\TrainingSession;
use App\Form\TeamType;
use App\Repository\AttendanceRepository;
use App\Repository\FixtureRepository;
use App\Repository\TeamRepository;
use App\Repository\TrainingSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/teams')]
class TeamController extends AbstractController
{
    #[Route('', name: 'app_team_index')]
    public function index(TeamRepository $teamRepository): Response
    {
        $teams = $teamRepository->findByCoach($this->getUser());
        return $this->render('team/index.html.twig', ['teams' => $teams]);
    }

    #[Route('/{id}', name: 'app_team_dashboard', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function dashboard(
        Team $team,
        FixtureRepository $fixtures,
        TrainingSessionRepository $trainings,
        AttendanceRepository $attendances,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        $players = $team->getPlayers()->toArray();
        usort($players, fn (Player $left, Player $right) => $left->getNumber() <=> $right->getNumber());

        $summary = $attendances->getSummaryByPlayerForTeam($team);
        $upcomingFixtures = $fixtures->findUpcomingForTeam($team, 8);
        $upcomingTrainings = $trainings->findUpcomingForTeam($team, 8);

        return $this->render('team/dashboard.html.twig', [
            'team' => $team,
            'players' => $players,
            'summary' => $summary,
            'totals' => $this->buildAttendanceTotals($summary),
            'events' => $this->buildCallupEvents($team, $upcomingFixtures, $upcomingTrainings, $attendances),
            'savedSheets' => $this->buildSavedSheetsView($team, $attendances->findSavedSheetsForTeam($team)),
            'topPresencePlayers' => $this->sortPlayersByPresence($players, $summary, true),
            'riskPresencePlayers' => $this->sortPlayersByPresence($players, $summary, false),
        ]);
    }

    #[Route('/new', name: 'app_team_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $team = new Team();
        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $team->setCoach($this->getUser());
            $em->persist($team);
            $em->flush();
            $this->addFlash('success', 'Équipe créée avec succès.');
            return $this->redirectToRoute('app_team_index');
        }

        return $this->render('team/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'app_team_edit')]
    public function edit(Team $team, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('COACH', $team);

        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Équipe mise à jour.');
            return $this->redirectToRoute('app_team_index');
        }

        return $this->render('team/edit.html.twig', ['form' => $form, 'team' => $team]);
    }

    #[Route('/{id}/delete', name: 'app_team_delete', methods: ['POST'])]
    public function delete(Team $team, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('COACH', $team);

        if ($this->isCsrfTokenValid('delete_team_' . $team->getId(), $request->request->get('_token'))) {
            $em->remove($team);
            $em->flush();
            $this->addFlash('success', 'Équipe supprimée.');
        }

        return $this->redirectToRoute('app_team_index');
    }

    /**
     * @param array<int, array{total:int,present:int,absent:int,excused:int,late:int,rate:int}> $summary
     *
     * @return array{total:int,present:int,absent:int,excused:int,late:int,rate:int}
     */
    private function buildAttendanceTotals(array $summary): array
    {
        $totals = ['total' => 0, 'present' => 0, 'absent' => 0, 'excused' => 0, 'late' => 0, 'rate' => 0];
        foreach ($summary as $row) {
            $totals['total'] += $row['total'];
            $totals['present'] += $row['present'];
            $totals['absent'] += $row['absent'];
            $totals['excused'] += $row['excused'];
            $totals['late'] += $row['late'];
        }

        if ($totals['total'] > 0) {
            $totals['rate'] = (int) round(($totals['present'] / $totals['total']) * 100);
        }

        return $totals;
    }

    /**
     * @param Fixture[] $fixtures
     * @param TrainingSession[] $trainings
     *
     * @return array<int, array{
     *     type:string,
     *     title:string,
     *     subtitle:string,
     *     date:\DateTimeImmutable,
     *     attendanceUrl:string,
     *     entered:int,
     *     missing:int,
     *     rate:int,
     *     status:string
     * }>
     */
    private function buildCallupEvents(
        Team $team,
        array $fixtures,
        array $trainings,
        AttendanceRepository $attendances,
    ): array {
        $events = [];
        $playersCount = $team->getPlayers()->count();

        foreach ($fixtures as $fixture) {
            $attendanceMap = $attendances->findIndexedForFixture($fixture);
            $events[] = [
                'type' => 'Match',
                'title' => 'vs '.$fixture->getOpponent(),
                'subtitle' => trim(($fixture->getCompetition() ?: 'Match').' · '.$fixture->getVenue(), ' ·'),
                'date' => $fixture->getMatchDate(),
                'attendanceUrl' => $this->generateUrl('app_fixture_attendance', [
                    'id' => $team->getId(),
                    'fixtureId' => $fixture->getId(),
                ]),
                ...$this->buildEventAttendanceState($playersCount, $attendanceMap),
            ];
        }

        foreach ($trainings as $training) {
            $attendanceMap = $attendances->findIndexedForTraining($training);
            $events[] = [
                'type' => 'Entraînement',
                'title' => $training->getTitle(),
                'subtitle' => trim($training->getDurationMinutes().' min'.($training->getFocus() ? ' · '.$training->getFocus() : ''), ' ·'),
                'date' => $training->getStartsAt(),
                'attendanceUrl' => $this->generateUrl('app_training_attendance', [
                    'id' => $team->getId(),
                    'sessionId' => $training->getId(),
                ]),
                ...$this->buildEventAttendanceState($playersCount, $attendanceMap),
            ];
        }

        usort($events, fn (array $left, array $right) => $left['date'] <=> $right['date']);

        return \array_slice($events, 0, 10);
    }

    /**
     * @param array<int, Attendance> $attendanceMap
     *
     * @return array{entered:int,missing:int,rate:int,status:string}
     */
    private function buildEventAttendanceState(int $playersCount, array $attendanceMap): array
    {
        $entered = \count($attendanceMap);
        $missing = max(0, $playersCount - $entered);

        return [
            'entered' => $entered,
            'missing' => $missing,
            'rate' => $playersCount > 0 ? (int) round(($entered / $playersCount) * 100) : 0,
            'status' => 0 === $entered ? 'À faire' : (0 === $missing ? 'Complet' : 'En cours'),
        ];
    }

    /**
     * @param array<int, array{
     *     type:string,
     *     event: Fixture|TrainingSession,
     *     title:string,
     *     date:\DateTimeImmutable,
     *     savedAt:\DateTimeImmutable,
     *     entered:int,
     *     present:int,
     *     absent:int,
     *     excused:int,
     *     late:int
     * }> $sheets
     *
     * @return array<int, array{
     *     type:string,
     *     title:string,
     *     date:\DateTimeImmutable,
     *     savedAt:\DateTimeImmutable,
     *     entered:int,
     *     present:int,
     *     absent:int,
     *     excused:int,
     *     late:int,
     *     rate:int,
     *     url:string
     * }>
     */
    private function buildSavedSheetsView(Team $team, array $sheets): array
    {
        return array_map(function (array $sheet) use ($team): array {
            $event = $sheet['event'];
            $url = $event instanceof Fixture
                ? $this->generateUrl('app_fixture_attendance', ['id' => $team->getId(), 'fixtureId' => $event->getId()])
                : $this->generateUrl('app_training_attendance', ['id' => $team->getId(), 'sessionId' => $event->getId()]);

            $entered = max(1, $sheet['entered']);

            return [
                'type' => $sheet['type'],
                'title' => $sheet['title'],
                'date' => $sheet['date'],
                'savedAt' => $sheet['savedAt'],
                'entered' => $sheet['entered'],
                'present' => $sheet['present'],
                'absent' => $sheet['absent'],
                'excused' => $sheet['excused'],
                'late' => $sheet['late'],
                'rate' => (int) round(($sheet['present'] / $entered) * 100),
                'url' => $url,
            ];
        }, $sheets);
    }

    /**
     * @param Player[] $players
     * @param array<int, array{total:int,present:int,absent:int,excused:int,late:int,rate:int}> $summary
     *
     * @return Player[]
     */
    private function sortPlayersByPresence(array $players, array $summary, bool $highest): array
    {
        usort($players, function (Player $left, Player $right) use ($summary, $highest): int {
            $leftRow = $summary[$left->getId()] ?? ['rate' => -1, 'total' => 0];
            $rightRow = $summary[$right->getId()] ?? ['rate' => -1, 'total' => 0];

            $comparison = [$rightRow['rate'], $rightRow['total'], $left->getNumber()] <=> [$leftRow['rate'], $leftRow['total'], $right->getNumber()];

            return $highest ? $comparison : -$comparison;
        });

        return \array_slice($players, 0, 5);
    }
}
