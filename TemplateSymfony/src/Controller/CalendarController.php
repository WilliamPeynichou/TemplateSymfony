<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Attendance;
use App\Entity\Fixture;
use App\Entity\Player;
use App\Entity\Team;
use App\Entity\TrainingSession;
use App\Entity\User;
use App\Repository\AttendanceRepository;
use App\Repository\FixtureRepository;
use App\Repository\TrainingSessionRepository;
use App\Service\IcsExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class CalendarController extends AbstractController
{
    #[Route('/teams/{id}/calendar', name: 'app_team_calendar', methods: ['GET'])]
    public function show(
        Team $team,
        FixtureRepository $fixtures,
        TrainingSessionRepository $trainings,
        AttendanceRepository $attendances,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);
        $attendanceSummary = $attendances->getSummaryByPlayerForTeam($team);

        return $this->render('calendar/show.html.twig', [
            'team' => $team,
            'fixtures' => $fixtures->findByTeamOrderedByDate($team),
            'trainings' => $trainings->findByTeamOrderedByDate($team),
            'attendanceSummary' => $attendanceSummary,
            'attendanceTotals' => $this->buildAttendanceTotals($attendanceSummary),
        ]);
    }

    #[Route('/teams/{id}/attendance', name: 'app_team_attendance_summary', methods: ['GET'])]
    public function summary(Team $team, Request $request, AttendanceRepository $attendances): Response
    {
        $this->denyAccessUnlessGranted('COACH', $team);

        $fromInput = trim((string) $request->query->get('from', ''));
        $toInput = trim((string) $request->query->get('to', ''));

        $from = $fromInput !== '' ? new \DateTimeImmutable($fromInput.' 00:00:00') : null;
        $to = $toInput !== '' ? new \DateTimeImmutable($toInput.' 23:59:59') : null;

        $summary = $attendances->getSummaryByPlayerForTeam($team, $from, $to);
        $totals = $this->buildAttendanceTotals($summary);
        $recentAttendances = $attendances->findRecentForTeam($team, $from, $to, 25);

        $players = $team->getPlayers()->toArray();
        usort($players, function (Player $left, Player $right) use ($summary): int {
            $leftRate = $summary[$left->getId()] ?? ['rate' => -1, 'total' => 0];
            $rightRate = $summary[$right->getId()] ?? ['rate' => -1, 'total' => 0];

            return [$rightRate['rate'], $rightRate['total'], $left->getNumber()] <=> [$leftRate['rate'], $leftRate['total'], $right->getNumber()];
        });

        return $this->render('calendar/summary.html.twig', [
            'team' => $team,
            'players' => $players,
            'summary' => $summary,
            'totals' => $totals,
            'recentAttendances' => $recentAttendances,
            'filters' => ['from' => $fromInput, 'to' => $toInput],
        ]);
    }

    #[Route('/teams/{id}/trainings/new', name: 'app_training_new', methods: ['GET', 'POST'])]
    public function newTraining(
        Team $team,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        if ($request->isMethod('POST')) {
            /** @var User $user */
            $user = $this->getUser();

            $session = (new TrainingSession())
                ->setTeam($team)
                ->setCoach($user)
                ->setTitle((string) $request->request->get('title'))
                ->setStartsAt(new \DateTimeImmutable((string) $request->request->get('starts_at')))
                ->setDurationMinutes((int) $request->request->get('duration_minutes', 60))
                ->setLocation($request->request->get('location') ?: null)
                ->setFocus($request->request->get('focus') ?: null)
                ->setPlan($request->request->get('plan') ?: null);

            $em->persist($session);
            $em->flush();
            $this->addFlash('success', 'Séance ajoutée.');

            return $this->redirectToRoute('app_team_calendar', ['id' => $team->getId()]);
        }

        return $this->render('calendar/new_training.html.twig', ['team' => $team]);
    }

    #[Route('/teams/{id}/trainings/{sessionId}/attendance', name: 'app_training_attendance', methods: ['GET', 'POST'])]
    public function trainingAttendance(
        Team $team,
        int $sessionId,
        Request $request,
        EntityManagerInterface $em,
        AttendanceRepository $attendances,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        $session = $em->find(TrainingSession::class, $sessionId);
        if (!$session instanceof TrainingSession || $session->getTeam()?->getId() !== $team->getId()) {
            throw $this->createNotFoundException('Séance introuvable.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('attendance_training_'.$session->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $this->saveTrainingAttendances($team, $session, $request, $em, $attendances);
            $this->addFlash('success', 'Présences de la séance enregistrées.');

            return $this->redirectToRoute('app_training_attendance', [
                'id' => $team->getId(),
                'sessionId' => $session->getId(),
            ]);
        }

        return $this->renderAttendanceForm(
            $team,
            'training',
            $session->getTitle(),
            $session->getStartsAt()->format('d/m/Y H:i').' · '.$session->getDurationMinutes().' min',
            $attendances->findIndexedForTraining($session),
            'attendance_training_'.$session->getId(),
        );
    }

    #[Route('/teams/{id}/fixtures/{fixtureId}/attendance', name: 'app_fixture_attendance', methods: ['GET', 'POST'])]
    public function fixtureAttendance(
        Team $team,
        int $fixtureId,
        Request $request,
        EntityManagerInterface $em,
        AttendanceRepository $attendances,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        $fixture = $em->find(Fixture::class, $fixtureId);
        if (!$fixture instanceof Fixture || $fixture->getTeam()?->getId() !== $team->getId()) {
            throw $this->createNotFoundException('Match introuvable.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('attendance_fixture_'.$fixture->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $this->saveFixtureAttendances($team, $fixture, $request, $em, $attendances);
            $this->addFlash('success', 'Présences du match enregistrées.');

            return $this->redirectToRoute('app_fixture_attendance', [
                'id' => $team->getId(),
                'fixtureId' => $fixture->getId(),
            ]);
        }

        return $this->renderAttendanceForm(
            $team,
            'fixture',
            'Match vs '.$fixture->getOpponent(),
            $fixture->getMatchDate()->format('d/m/Y H:i').($fixture->getCompetition() ? ' · '.$fixture->getCompetition() : ''),
            $attendances->findIndexedForFixture($fixture),
            'attendance_fixture_'.$fixture->getId(),
        );
    }

    #[Route('/teams/{id}/calendar.ics', name: 'app_team_calendar_ics', methods: ['GET'])]
    public function ics(
        Team $team,
        FixtureRepository $fixtures,
        TrainingSessionRepository $trainings,
        IcsExporter $exporter,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        $body = $exporter->export(
            $fixtures->findByTeamOrderedByDate($team),
            $trainings->findByTeamOrderedByDate($team),
            calendarName: 'Andfield — '.$team->getName(),
        );

        return new Response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="team-%d.ics"', $team->getId()),
        ]);
    }

    /**
     * @param array<int, Attendance> $attendanceMap
     */
    private function renderAttendanceForm(
        Team $team,
        string $eventType,
        string $eventTitle,
        string $eventSubtitle,
        array $attendanceMap,
        string $csrfTokenId,
    ): Response {
        $players = $team->getPlayers()->toArray();
        usort($players, fn (Player $left, Player $right) => $left->getNumber() <=> $right->getNumber());

        return $this->render('calendar/attendance.html.twig', [
            'team' => $team,
            'players' => $players,
            'eventType' => $eventType,
            'eventTitle' => $eventTitle,
            'eventSubtitle' => $eventSubtitle,
            'attendanceMap' => $attendanceMap,
            'statuses' => Attendance::STATUSES,
            'csrfTokenId' => $csrfTokenId,
            'totals' => $this->buildAttendanceSheetTotals($players, $attendanceMap),
        ]);
    }

    private function saveTrainingAttendances(
        Team $team,
        TrainingSession $session,
        Request $request,
        EntityManagerInterface $em,
        AttendanceRepository $attendances,
    ): void {
        foreach ($team->getPlayers() as $player) {
            $row = $request->request->all('attendance')[$player->getId()] ?? null;
            if (!\is_array($row)) {
                continue;
            }

            $attendance = $attendances->findOneForTraining($player, $session) ?? (new Attendance())
                ->setPlayer($player)
                ->setTrainingSession($session);

            $this->fillAttendance($attendance, $row);
            $em->persist($attendance);
        }

        $em->flush();
    }

    private function saveFixtureAttendances(
        Team $team,
        Fixture $fixture,
        Request $request,
        EntityManagerInterface $em,
        AttendanceRepository $attendances,
    ): void {
        foreach ($team->getPlayers() as $player) {
            $row = $request->request->all('attendance')[$player->getId()] ?? null;
            if (!\is_array($row)) {
                continue;
            }

            $attendance = $attendances->findOneForFixture($player, $fixture) ?? (new Attendance())
                ->setPlayer($player)
                ->setFixture($fixture);

            $this->fillAttendance($attendance, $row);
            $em->persist($attendance);
        }

        $em->flush();
    }

    /**
     * @param array{status?: string, reason?: string} $row
     */
    private function fillAttendance(Attendance $attendance, array $row): void
    {
        $user = $this->getUser();
        $status = (string) ($row['status'] ?? Attendance::STATUS_PRESENT);
        $reason = ($row['reason'] ?? '') !== '' ? (string) $row['reason'] : null;
        if (!\in_array($status, [Attendance::STATUS_ABSENT, Attendance::STATUS_EXCUSED], true)) {
            $reason = null;
        }

        $attendance
            ->setStatus($status)
            ->setReason($reason)
            ->setRecordedBy($user instanceof User ? $user : null);
        $attendance->touch();
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
     * @param Player[] $players
     * @param array<int, Attendance> $attendanceMap
     *
     * @return array{players:int,present:int,absent:int,excused:int,late:int}
     */
    private function buildAttendanceSheetTotals(array $players, array $attendanceMap): array
    {
        $totals = [
            'players' => \count($players),
            'present' => 0,
            'absent' => 0,
            'excused' => 0,
            'late' => 0,
        ];

        foreach ($players as $player) {
            $status = $attendanceMap[$player->getId() ?? 0]?->getStatus() ?? Attendance::STATUS_PRESENT;
            if (isset($totals[$status])) {
                ++$totals[$status];
            }
        }

        return $totals;
    }
}
