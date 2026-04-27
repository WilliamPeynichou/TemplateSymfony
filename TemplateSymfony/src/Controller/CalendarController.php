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
        Request $request,
        FixtureRepository $fixtures,
        TrainingSessionRepository $trainings,
        AttendanceRepository $attendances,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        $monthInput = (string) $request->query->get('month', '');
        try {
            $cursor = $monthInput !== ''
                ? new \DateTimeImmutable($monthInput.'-01')
                : new \DateTimeImmutable('first day of this month');
        } catch (\Exception) {
            $cursor = new \DateTimeImmutable('first day of this month');
        }

        $monthStart = $cursor->modify('first day of this month')->setTime(0, 0);
        $monthEnd   = $cursor->modify('last day of this month')->setTime(23, 59, 59);

        // Grille : commence au lundi avant/égal au 1er du mois
        $gridStart = $monthStart;
        while ((int) $gridStart->format('N') !== 1) {
            $gridStart = $gridStart->modify('-1 day');
        }
        // Termine au dimanche après/égal au dernier du mois
        $gridEnd = $monthEnd;
        while ((int) $gridEnd->format('N') !== 7) {
            $gridEnd = $gridEnd->modify('+1 day');
        }

        // Indexer les events par 'Y-m-d'
        $eventsByDay = [];
        foreach ($fixtures->findByTeamOrderedByDate($team) as $f) {
            if ($f->getMatchDate() < $gridStart || $f->getMatchDate() > $gridEnd) continue;
            $key      = $f->getMatchDate()->format('Y-m-d');
            $strategy = $f->getTacticalStrategy();
            $eventsByDay[$key][] = [
                'type'         => 'match',
                'time'         => $f->getMatchDate()->format('H:i'),
                'title'        => 'vs '.$f->getOpponent(),
                'meta'         => trim(($f->getCompetition() ?: '').' · '.$f->getVenue(), ' ·'),
                'url'          => $this->generateUrl('app_fixture_attendance', ['id' => $team->getId(), 'fixtureId' => $f->getId()]),
                'date'         => $f->getMatchDate(),
                'strategyName' => $strategy?->getName(),
                'strategyUrl'  => $strategy
                    ? $this->generateUrl('app_strategy_edit', ['teamId' => $team->getId(), 'id' => $strategy->getId()])
                    : null,
                'formation'    => $strategy?->getFormation(),
            ];
        }
        foreach ($trainings->findByTeamOrderedByDate($team) as $t) {
            if ($t->getStartsAt() < $gridStart || $t->getStartsAt() > $gridEnd) continue;
            $key = $t->getStartsAt()->format('Y-m-d');
            $eventsByDay[$key][] = [
                'type'  => 'training',
                'time'  => $t->getStartsAt()->format('H:i'),
                'title' => $t->getTitle(),
                'meta'  => $t->getDurationMinutes().' min'.($t->getFocus() ? ' · '.$t->getFocus() : ''),
                'url'   => $this->generateUrl('app_training_attendance', ['id' => $team->getId(), 'sessionId' => $t->getId()]),
                'date'  => $t->getStartsAt(),
            ];
        }
        foreach ($eventsByDay as &$list) {
            usort($list, fn($a, $b) => $a['date'] <=> $b['date']);
        }
        unset($list);

        // Construire les semaines
        $weeks = [];
        $day   = $gridStart;
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        while ($day <= $gridEnd) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $day->format('Y-m-d');
                $week[] = [
                    'date'         => $day,
                    'dayNum'       => (int) $day->format('j'),
                    'isCurrent'    => $day->format('Y-m') === $monthStart->format('Y-m'),
                    'isToday'      => $key === $today,
                    'events'       => $eventsByDay[$key] ?? [],
                ];
                $day = $day->modify('+1 day');
            }
            $weeks[] = $week;
        }

        $attendanceSummary = $attendances->getSummaryByPlayerForTeam($team);

        return $this->render('calendar/show.html.twig', [
            'team'             => $team,
            'monthStart'       => $monthStart,
            'prevMonth'        => $monthStart->modify('-1 month')->format('Y-m'),
            'nextMonth'        => $monthStart->modify('+1 month')->format('Y-m'),
            'currentMonth'     => (new \DateTimeImmutable('first day of this month'))->format('Y-m'),
            'weeks'            => $weeks,
            'attendanceTotals' => $this->buildAttendanceTotals($attendanceSummary),
        ]);
    }

    #[Route('/teams/{id}/attendance', name: 'app_team_attendance_summary', methods: ['GET'])]
    public function summary(
        Team $team,
        Request $request,
        AttendanceRepository $attendances,
        FixtureRepository $fixtures,
        TrainingSessionRepository $trainings,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        $fromInput = trim((string) $request->query->get('from', ''));
        $toInput   = trim((string) $request->query->get('to', ''));
        $preset    = trim((string) $request->query->get('preset', ''));

        if ($fromInput === '' && $toInput === '' && $preset !== '' && $preset !== 'all') {
            $today     = new \DateTimeImmutable('today');
            $fromInput = match ($preset) {
                '7' => $today->modify('-7 days')->format('Y-m-d'),
                '30' => $today->modify('-30 days')->format('Y-m-d'),
                '90' => $today->modify('-90 days')->format('Y-m-d'),
                '365' => $today->modify('-365 days')->format('Y-m-d'),
                default => '',
            };
            if ($fromInput !== '') {
                $toInput = $today->format('Y-m-d');
            }
        }

        $from = $fromInput !== '' ? new \DateTimeImmutable($fromInput.' 00:00:00') : null;
        $to   = $toInput !== '' ? new \DateTimeImmutable($toInput.' 23:59:59') : null;

        $summary = $attendances->getSummaryByPlayerForTeam($team, $from, $to);
        $totals  = $this->buildAttendanceTotals($summary);
        $recentAttendances = $attendances->findRecentForTeam($team, $from, $to, 40);

        $players = $team->getPlayers()->toArray();
        usort($players, function (Player $left, Player $right) use ($summary): int {
            $leftRate  = $summary[$left->getId()] ?? ['rate' => -1, 'total' => 0];
            $rightRate = $summary[$right->getId()] ?? ['rate' => -1, 'total' => 0];

            return [$rightRate['rate'], $rightRate['total'], $left->getNumber()] <=> [$leftRate['rate'], $leftRate['total'], $right->getNumber()];
        });

        $periodLabel = 'Toute la période';
        if ($from instanceof \DateTimeImmutable && $to instanceof \DateTimeImmutable) {
            $periodLabel = $from->format('d/m/Y').' — '.$to->format('d/m/Y');
        } elseif ($from instanceof \DateTimeImmutable) {
            $periodLabel = 'Depuis le '.$from->format('d/m/Y');
        } elseif ($to instanceof \DateTimeImmutable) {
            $periodLabel = 'Jusqu\'au '.$to->format('d/m/Y');
        }

        $playersWithData = 0;
        foreach ($summary as $row) {
            if (($row['total'] ?? 0) > 0) {
                ++$playersWithData;
            }
        }

        $sheetsRaw = $attendances->findSavedSheetsForTeam($team);
        $savedSheetsPreview = array_map(function (array $sheet) use ($team): array {
            $event = $sheet['event'];
            $url   = $event instanceof Fixture
                ? $this->generateUrl('app_fixture_attendance', ['id' => $team->getId(), 'fixtureId' => $event->getId()])
                : $this->generateUrl('app_training_attendance', ['id' => $team->getId(), 'sessionId' => $event->getId()]);
            $entered = max(1, $sheet['entered']);

            return [
                'type'    => $sheet['type'],
                'title'   => $sheet['title'],
                'date'    => $sheet['date'],
                'savedAt' => $sheet['savedAt'],
                'rate'    => (int) round(($sheet['present'] / $entered) * 100),
                'url'     => $url,
                'present' => $sheet['present'],
                'entered' => $sheet['entered'],
            ];
        }, \array_slice($sheetsRaw, 0, 8));

        $upcomingEvents = $this->buildCalendarEvents(
            $team,
            $fixtures->findUpcomingForTeam($team, 8),
            $trainings->findUpcomingForTeam($team, 8),
            $attendances,
        );

        return $this->render('calendar/summary.html.twig', [
            'team'               => $team,
            'players'            => $players,
            'summary'            => $summary,
            'totals'             => $totals,
            'recentAttendances'  => $recentAttendances,
            'filters'            => ['from' => $fromInput, 'to' => $toInput, 'preset' => $preset],
            'periodLabel'        => $periodLabel,
            'savedSheetsPreview' => $savedSheetsPreview,
            'upcomingEvents'     => \array_slice($upcomingEvents, 0, 10),
            'playersCount'       => \count($players),
            'playersWithData'    => $playersWithData,
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
            $this->addFlash('success', 'Feuille de présence enregistrée et liée à la séance.');

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
            $this->addFlash('success', 'Feuille de présence enregistrée et liée au match.');

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

            if (($row['status'] ?? '') === '') {
                if (null !== $attendance->getId()) {
                    $em->remove($attendance);
                }
                continue;
            }

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

            if (($row['status'] ?? '') === '') {
                if (null !== $attendance->getId()) {
                    $em->remove($attendance);
                }
                continue;
            }

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
     * @return array{players:int,present:int,absent:int,excused:int,late:int,unmarked:int}
     */
    private function buildAttendanceSheetTotals(array $players, array $attendanceMap): array
    {
        $totals = [
            'players' => \count($players),
            'present' => 0,
            'absent' => 0,
            'excused' => 0,
            'late' => 0,
            'unmarked' => 0,
        ];

        foreach ($players as $player) {
            $attendance = $attendanceMap[$player->getId() ?? 0] ?? null;
            $status = $attendance?->getStatus() ?? 'unmarked';
            if (isset($totals[$status])) {
                ++$totals[$status];
            }
        }

        return $totals;
    }

    /**
     * @param Fixture[] $fixtures
     * @param TrainingSession[] $trainings
     *
     * @return array<int, array{type:string,title:string,meta:string,date:\DateTimeImmutable,url:string,rate:int,status:string}>
     */
    private function buildCalendarEvents(
        Team $team,
        array $fixtures,
        array $trainings,
        AttendanceRepository $attendances,
    ): array {
        $events = [];
        $playersCount = max(1, $team->getPlayers()->count());
        $now = new \DateTimeImmutable();

        foreach ($fixtures as $fixture) {
            if ($fixture->getMatchDate() < $now) {
                continue;
            }
            $entered = \count($attendances->findIndexedForFixture($fixture));
            $events[] = [
                'type' => 'Match',
                'title' => 'vs '.$fixture->getOpponent(),
                'meta' => trim(($fixture->getCompetition() ?: 'Match').' · '.$fixture->getVenue(), ' ·'),
                'date' => $fixture->getMatchDate(),
                'url' => $this->generateUrl('app_fixture_attendance', ['id' => $team->getId(), 'fixtureId' => $fixture->getId()]),
                'rate' => (int) round(($entered / $playersCount) * 100),
                'status' => $entered === 0 ? 'À faire' : ($entered >= $playersCount ? 'Complet' : 'En cours'),
            ];
        }

        foreach ($trainings as $training) {
            if ($training->getStartsAt() < $now) {
                continue;
            }
            $entered = \count($attendances->findIndexedForTraining($training));
            $events[] = [
                'type' => 'Entraînement',
                'title' => $training->getTitle(),
                'meta' => trim($training->getDurationMinutes().' min'.($training->getFocus() ? ' · '.$training->getFocus() : ''), ' ·'),
                'date' => $training->getStartsAt(),
                'url' => $this->generateUrl('app_training_attendance', ['id' => $team->getId(), 'sessionId' => $training->getId()]),
                'rate' => (int) round(($entered / $playersCount) * 100),
                'status' => $entered === 0 ? 'À faire' : ($entered >= $playersCount ? 'Complet' : 'En cours'),
            ];
        }

        usort($events, fn (array $left, array $right) => $left['date'] <=> $right['date']);

        return $events;
    }
}
