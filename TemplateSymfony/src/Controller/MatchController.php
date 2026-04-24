<?php

namespace App\Controller;

use App\Entity\Fixture;
use App\Entity\Player;
use App\Entity\TacticalStrategy;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\AttendanceRepository;
use App\Repository\PlayerMatchStatRepository;
use App\Repository\TacticalStrategyRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/matches')]
class MatchController extends AbstractController
{
    #[Route('/prepare', name: 'app_match_prepare', methods: ['GET', 'POST'])]
    public function prepare(
        Request $request,
        TeamRepository $teamRepository,
        TacticalStrategyRepository $strategyRepository,
        AttendanceRepository $attendanceRepository,
        PlayerMatchStatRepository $playerMatchStatRepository,
        EntityManagerInterface $em,
    ): Response
    {
        /** @var User $coach */
        $coach = $this->getUser();
        $teams = $teamRepository->findByCoach($coach);

        $selectedTeamId = (int) ($request->isMethod('POST')
            ? $request->request->get('team_id', 0)
            : $request->query->get('team_id', 0));
        $selectedTeam = $this->findSelectedTeam($teams, $selectedTeamId);
        $opponentName = trim((string) ($request->isMethod('POST')
            ? $request->request->get('opponent_name', '')
            : $request->query->get('opponent_name', '')));

        $matchContext = $this->buildMatchContext($request);
        $selectedTeamPreview = $selectedTeam instanceof Team
            ? $this->buildTeamPreparationInsights($selectedTeam, $attendanceRepository, $playerMatchStatRepository)
            : null;

        $strategiesByTeam = [];
        $selectedStrategy = null;
        foreach ($teams as $team) {
            $strategies = $strategyRepository->findByTeam($team);
            $strategiesByTeam[$team->getId()] = $this->buildStrategyBankForTeam($strategies);

            if ($selectedTeam instanceof Team && $team->getId() === $selectedTeam->getId()) {
                $selectedStrategyId = (int) $matchContext['strategyId'];
                foreach ($strategies as $strategyCandidate) {
                    if ($selectedStrategyId > 0 && $strategyCandidate->getId() === $selectedStrategyId) {
                        $selectedStrategy = $strategyCandidate;
                        break;
                    }
                }

                if (!$selectedStrategy) {
                    $selectedStrategy = $strategyRepository->findDefaultForTeam($selectedTeam) ?? ($strategies[0] ?? null);
                }
            }
        }

        if (is_array($selectedTeamPreview) && $selectedStrategy instanceof TacticalStrategy) {
            $matchContext = $this->applyStrategyDefaultsToMatchContext($matchContext, $selectedStrategy, $request);
        }

        if (is_array($selectedTeamPreview) && !$request->request->has('shape') && !$request->query->has('shape')) {
            $matchContext['shape'] = $selectedStrategy instanceof TacticalStrategy && $selectedStrategy->isFormation()
                ? $selectedStrategy->getFormation()
                : $selectedTeamPreview['recommendedShape'];
        }

        if ($selectedStrategy instanceof TacticalStrategy) {
            $matchContext['strategyId'] = $selectedStrategy->getId() ?? 0;
            $matchContext['strategyName'] = $selectedStrategy->getName();
            $matchContext['strategyMode'] = $selectedStrategy->getMode();
        }

        if ($request->isMethod('POST')) {
            if (!$selectedTeam) {
                $this->addFlash('error', 'Sélectionnez une de vos équipes pour préparer ce match.');
            } elseif ($opponentName === '') {
                $this->addFlash('error', 'Saisissez le nom de l\'adversaire.');
            } else {
                $kickoffAt = null;
                if ($matchContext['kickoff'] !== '') {
                    try {
                        $kickoffAt = new \DateTimeImmutable($matchContext['kickoff']);
                    } catch (\Exception) {
                        $this->addFlash('error', 'La date du coup d\'envoi est invalide.');

                        return $this->render('match/prepare.html.twig', [
                            'teams' => $teams,
                            'selectedTeamId' => $selectedTeamId,
                            'selectedTeam' => $selectedTeam,
                            'opponentName' => $opponentName,
                            'selectedTeamPreview' => $selectedTeamPreview,
                            'matchContext' => $matchContext,
                            'strategiesByTeam' => $strategiesByTeam,
                            'selectedStrategy' => $selectedStrategy instanceof TacticalStrategy ? $this->buildStrategyCardData($selectedStrategy) : null,
                            'competitions' => [
                                Fixture::COMPETITION_FRIENDLY => 'Amical',
                                Fixture::COMPETITION_LEAGUE => 'Championnat',
                                Fixture::COMPETITION_CUP => 'Coupe',
                            ],
                        ]);
                    }
                }

                $strategy = null;
                if (($matchContext['strategyId'] ?? 0) > 0) {
                    $candidate = $strategyRepository->find($matchContext['strategyId']);
                    if ($candidate instanceof TacticalStrategy && $candidate->getTeam()?->getId() === $selectedTeam->getId()) {
                        $strategy = $candidate;
                        $strategy->incrementUsage();
                        $strategy->touch();
                    }
                }

                $fixture = null;
                if ($kickoffAt instanceof \DateTimeImmutable) {
                    $fixture = (new Fixture())
                        ->setTeam($selectedTeam)
                        ->setCoach($coach)
                        ->setOpponent($opponentName)
                        ->setMatchDate($kickoffAt)
                        ->setVenue($matchContext['venue'])
                        ->setCompetition($matchContext['competition'] !== '' ? $matchContext['competition'] : null)
                        ->setNotes($this->buildFixtureNotes($matchContext))
                        ->setTacticalStrategy($strategy);

                    $em->persist($fixture);
                }

                if ($strategy instanceof TacticalStrategy) {
                    $em->persist($strategy);
                }
                $em->flush();

                $matchContext['strategyId'] = $strategy?->getId() ?? 0;
                $matchContext['strategyName'] = $strategy?->getName() ?? '';

                $request->getSession()->set('match_preparation', [
                    'scenario' => 'real_vs_fictional',
                    'context' => $matchContext,
                    'fixtureId' => $fixture?->getId(),
                    'homeInsights' => $selectedTeamPreview,
                    'home' => $this->buildRealTeamData($selectedTeam, 'home', $selectedTeam->getName(), $attendanceRepository, $playerMatchStatRepository),
                    'away' => $this->buildFictionalTeamData('away', $opponentName, ''),
                ]);

                $this->addFlash('success', $fixture instanceof Fixture
                    ? ($strategy instanceof TacticalStrategy
                        ? 'Match ajouté au calendrier et tactique assignée.'
                        : 'Match ajouté au calendrier.')
                    : ($strategy instanceof TacticalStrategy
                        ? 'Tactique sélectionnée pour ce match.'
                        : 'Préparation de match enregistrée.'));

                return $this->redirectToRoute('app_match_board');
            }
        }

        return $this->render('match/prepare.html.twig', [
            'teams' => $teams,
            'selectedTeamId' => $selectedTeamId,
            'selectedTeam' => $selectedTeam,
            'opponentName' => $opponentName,
            'selectedTeamPreview' => $selectedTeamPreview,
            'matchContext' => $matchContext,
            'strategiesByTeam' => $strategiesByTeam,
            'selectedStrategy' => $selectedStrategy instanceof TacticalStrategy ? $this->buildStrategyCardData($selectedStrategy) : null,
            'competitions' => [
                Fixture::COMPETITION_FRIENDLY => 'Amical',
                Fixture::COMPETITION_LEAGUE => 'Championnat',
                Fixture::COMPETITION_CUP => 'Coupe',
            ],
        ]);
    }

    #[Route('/board', name: 'app_match_board', methods: ['GET'])]
    public function board(Request $request): Response
    {
        $preparation = $request->getSession()->get('match_preparation');
        if (!is_array($preparation) || !isset($preparation['home'], $preparation['away'])) {
            $this->addFlash('error', 'Préparez d\'abord un match avant d\'ouvrir le terrain.');
            return $this->redirectToRoute('app_match_prepare');
        }

        return $this->render('match/board.html.twig', [
            'match' => $preparation,
            'context' => $preparation['context'] ?? null,
            'homeInsights' => $preparation['homeInsights'] ?? null,
            'awayInsights' => $preparation['awayInsights'] ?? null,
        ]);
    }

    /**
     * @param Team[] $teams
     */
    private function findSelectedTeam(array $teams, int $id): ?Team
    {
        foreach ($teams as $team) {
            if ($team->getId() === $id) {
                return $team;
            }
        }

        return null;
    }

    /**
     * @return array{
     *   name: string,
     *   label: string,
     *   side: string,
     *   type: string,
     *   players: list<array{id: string, name: string, shortName: string, number: int, position: string, side: string, photo: ?string, status: string, statusReason: ?string, minutes: int, avgRating: ?float, attendanceRate: int}>
     * }
     */
    private function buildRealTeamData(
        Team $team,
        string $side,
        string $label,
        AttendanceRepository $attendanceRepository,
        PlayerMatchStatRepository $playerMatchStatRepository,
    ): array
    {
        $players = $team->getPlayers()->toArray();
        $attendanceSummary = $attendanceRepository->getSummaryByPlayerForTeam($team);
        usort($players, function (Player $left, Player $right) use ($attendanceSummary): int {
            $leftUnavailable = $left->getStatus() !== Player::STATUS_PRESENT ? 1 : 0;
            $rightUnavailable = $right->getStatus() !== Player::STATUS_PRESENT ? 1 : 0;

            return [$leftUnavailable, $left->getNumber()] <=> [$rightUnavailable, $right->getNumber()];
        });

        return [
            'name' => (string) $team->getName(),
            'label' => $label,
            'side' => $side,
            'type' => 'real',
            'players' => array_map(
                fn (Player $player) => [
                    'id' => $side . '-player-' . $player->getId(),
                    'name' => $player->getFullName(),
                    'shortName' => (string) $player->getLastName(),
                    'number' => (int) $player->getNumber(),
                    'position' => (string) $player->getPosition(),
                    'side' => $side,
                    'photo' => $player->getPhoto(),
                    'status' => $player->getStatus(),
                    'statusReason' => $player->getStatusReason(),
                    'minutes' => $playerMatchStatRepository->aggregateForPlayer($player)['minutes'],
                    'avgRating' => $playerMatchStatRepository->aggregateForPlayer($player)['avgRating'],
                    'attendanceRate' => $attendanceSummary[$player->getId()]['rate'] ?? 0,
                ],
                $players
            ),
        ];
    }

    /**
     * @return array{
     *   name: string,
     *   label: string,
     *   side: string,
     *   type: string,
     *   players: list<array{id: string, name: string, shortName: string, number: int, position: string, side: string, photo: ?string}>
     * }
     */
    private function buildFictionalTeamData(string $side, string $name, string $rawPlayers): array
    {
        $players = $this->parseFictionalPlayers($side, $rawPlayers);
        if ($players === []) {
            $players = $this->getDefaultFictionalPlayers($side);
        }

        usort($players, fn (array $left, array $right) => $left['number'] <=> $right['number']);

        return [
            'name' => $name !== '' ? $name : 'Équipe fictive',
            'label' => 'Équipe fictive',
            'side' => $side,
            'type' => 'fictional',
            'players' => $players,
        ];
    }

    /**
     * @return list<array{id: string, name: string, shortName: string, number: int, position: string, side: string, photo: null}>
     */
    private function parseFictionalPlayers(string $side, string $rawPlayers): array
    {
        $lines = preg_split('/\R/', $rawPlayers) ?: [];
        $players = [];
        $allowedPositions = array_values(Player::POSITIONS);

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode(',', $line));
            if (count($parts) < 3 || !is_numeric($parts[0])) {
                continue;
            }

            $position = strtoupper($parts[2]);
            if (!in_array($position, $allowedPositions, true)) {
                continue;
            }

            $name = $parts[1];
            $players[] = [
                'id' => sprintf('%s-fictional-%d', $side, $index + 1),
                'name' => $name,
                'shortName' => $this->extractShortName($name),
                'number' => (int) $parts[0],
                'position' => $position,
                'side' => $side,
                'photo' => null,
            ];
        }

        return $players;
    }

    /**
     * @return list<array{id: string, name: string, shortName: string, number: int, position: string, side: string, photo: null}>
     */
    private function getDefaultFictionalPlayers(string $side): array
    {
        return [
            ['id' => $side . '-fictional-1', 'name' => 'Gardien fictif', 'shortName' => 'Gardien', 'number' => 1, 'position' => 'GK', 'side' => $side, 'photo' => null],
            ['id' => $side . '-fictional-2', 'name' => 'Défenseur droit fictif', 'shortName' => 'Def. droit', 'number' => 2, 'position' => 'RB', 'side' => $side, 'photo' => null],
            ['id' => $side . '-fictional-3', 'name' => 'Lateral gauche fictif', 'shortName' => 'Lat. gauche', 'number' => 3, 'position' => 'LB', 'side' => $side, 'photo' => null],
            ['id' => $side . '-fictional-4', 'name' => 'Defenseur central A', 'shortName' => 'Central A', 'number' => 4, 'position' => 'CB', 'side' => $side, 'photo' => null],
            ['id' => $side . '-fictional-5', 'name' => 'Defenseur central B', 'shortName' => 'Central B', 'number' => 5, 'position' => 'CB', 'side' => $side, 'photo' => null],
            ['id' => $side . '-fictional-6', 'name' => 'Milieu sentinelle', 'shortName' => 'Sentinelle', 'number' => 6, 'position' => 'CDM', 'side' => $side, 'photo' => null],
            ['id' => $side . '-fictional-7', 'name' => 'Ailier droit fictif', 'shortName' => 'Ailier D', 'number' => 7, 'position' => 'RW', 'side' => $side, 'photo' => null],
            ['id' => $side . '-fictional-8', 'name' => 'Milieu relayeur', 'shortName' => 'Relayeur', 'number' => 8, 'position' => 'CM', 'side' => $side, 'photo' => null],
            ['id' => $side . '-fictional-9', 'name' => 'Buteur fictif', 'shortName' => 'Buteur', 'number' => 9, 'position' => 'ST', 'side' => $side, 'photo' => null],
            ['id' => $side . '-fictional-10', 'name' => 'Milieu creatif', 'shortName' => 'Creatif', 'number' => 10, 'position' => 'CAM', 'side' => $side, 'photo' => null],
            ['id' => $side . '-fictional-11', 'name' => 'Ailier gauche fictif', 'shortName' => 'Ailier G', 'number' => 11, 'position' => 'LW', 'side' => $side, 'photo' => null],
        ];
    }

    private function extractShortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if ($parts === []) {
            return $name;
        }

        return (string) end($parts);
    }

    private function buildMatchContext(Request $request): array
    {
        return [
            'competition' => trim((string) $request->request->get('competition', $request->query->get('competition', Fixture::COMPETITION_FRIENDLY))),
            'kickoff' => trim((string) $request->request->get('kickoff', $request->query->get('kickoff', ''))),
            'venue' => trim((string) $request->request->get('venue', $request->query->get('venue', 'home'))),
            'objective' => trim((string) $request->request->get('objective', $request->query->get('objective', ''))),
            'shape' => trim((string) $request->request->get('shape', $request->query->get('shape', ''))),
            'pressingPlan' => trim((string) $request->request->get('pressing_plan', $request->query->get('pressing_plan', ''))),
            'inPossessionPlan' => trim((string) $request->request->get('in_possession_plan', $request->query->get('in_possession_plan', ''))),
            'transitionPlan' => trim((string) $request->request->get('transition_plan', $request->query->get('transition_plan', ''))),
            'setPiecesPlan' => trim((string) $request->request->get('set_pieces_plan', $request->query->get('set_pieces_plan', ''))),
            'watchouts' => trim((string) $request->request->get('watchouts', $request->query->get('watchouts', ''))),
            'strategyId' => (int) $request->request->get('strategy_id', $request->query->get('strategy_id', 0)),
            'strategyName' => '',
            'strategyMode' => '',
        ];
    }

    /**
     * @param TacticalStrategy[] $strategies
     *
     * @return array{
     *   all: array<int, array<string, mixed>>,
     *   free: array<int, array<string, mixed>>,
     *   formation: array<int, array<string, mixed>>,
     *   counts: array{total:int, free:int, formation:int},
     *   defaultId: int
     * }
     */
    private function buildStrategyBankForTeam(array $strategies): array
    {
        $cards = array_map(fn (TacticalStrategy $strategy) => $this->buildStrategyCardData($strategy), $strategies);
        $free = array_values(array_filter($cards, static fn (array $card): bool => $card['mode'] === TacticalStrategy::MODE_FREE));
        $formation = array_values(array_filter($cards, static fn (array $card): bool => $card['mode'] === TacticalStrategy::MODE_FORMATION));

        $defaultId = 0;
        foreach ($cards as $card) {
            if ($card['isDefault']) {
                $defaultId = $card['id'];
                break;
            }
        }

        return [
            'all' => $cards,
            'free' => $free,
            'formation' => $formation,
            'counts' => [
                'total' => \count($cards),
                'free' => \count($free),
                'formation' => \count($formation),
            ],
            'defaultId' => $defaultId,
        ];
    }

    private function buildStrategyCardData(TacticalStrategy $strategy): array
    {
        $notesCount = 0;
        foreach ([$strategy->getInPossessionNotes(), $strategy->getOutOfPossessionNotes(), $strategy->getTransitionNotes(), $strategy->getSetPieceNotes()] as $note) {
            if (\is_string($note) && trim($note) !== '') {
                ++$notesCount;
            }
        }

        return [
            'id' => $strategy->getId() ?? 0,
            'name' => $strategy->getName(),
            'mode' => $strategy->getMode(),
            'modeLabel' => $strategy->isFree() ? 'Plan libre' : 'Plan formation',
            'formation' => $strategy->getFormation(),
            'isDefault' => $strategy->isDefault(),
            'usageCount' => $strategy->getUsageCount(),
            'updatedAtLabel' => $strategy->getUpdatedAt()->format('d/m/Y H:i'),
            'notesCount' => $notesCount,
            'inPossessionNotes' => $strategy->getInPossessionNotes() ?? '',
            'outOfPossessionNotes' => $strategy->getOutOfPossessionNotes() ?? '',
            'transitionNotes' => $strategy->getTransitionNotes() ?? '',
            'setPieceNotes' => $strategy->getSetPieceNotes() ?? '',
        ];
    }

    private function applyStrategyDefaultsToMatchContext(array $matchContext, TacticalStrategy $strategy, Request $request): array
    {
        $shapeProvided = $request->request->has('shape') || $request->query->has('shape');
        if ($strategy->isFormation() && !$shapeProvided) {
            $matchContext['shape'] = $strategy->getFormation();
        }

        if ($matchContext['objective'] === '' && $strategy->getDescription()) {
            $matchContext['objective'] = $strategy->getDescription();
        }

        if ($matchContext['pressingPlan'] === '' && $strategy->getOutOfPossessionNotes()) {
            $matchContext['pressingPlan'] = $strategy->getOutOfPossessionNotes();
        }

        if ($matchContext['inPossessionPlan'] === '' && $strategy->getInPossessionNotes()) {
            $matchContext['inPossessionPlan'] = $strategy->getInPossessionNotes();
        }

        if ($matchContext['transitionPlan'] === '' && $strategy->getTransitionNotes()) {
            $matchContext['transitionPlan'] = $strategy->getTransitionNotes();
        }

        if ($matchContext['setPiecesPlan'] === '' && $strategy->getSetPieceNotes()) {
            $matchContext['setPiecesPlan'] = $strategy->getSetPieceNotes();
        }

        return $matchContext;
    }

    private function buildFixtureNotes(array $matchContext): ?string
    {
        $sections = [];

        if (($matchContext['objective'] ?? '') !== '') {
            $sections[] = 'Objectif: '.$matchContext['objective'];
        }
        if (($matchContext['pressingPlan'] ?? '') !== '') {
            $sections[] = 'Sans ballon: '.$matchContext['pressingPlan'];
        }
        if (($matchContext['inPossessionPlan'] ?? '') !== '') {
            $sections[] = 'Avec ballon: '.$matchContext['inPossessionPlan'];
        }
        if (($matchContext['transitionPlan'] ?? '') !== '') {
            $sections[] = 'Transitions: '.$matchContext['transitionPlan'];
        }
        if (($matchContext['setPiecesPlan'] ?? '') !== '') {
            $sections[] = 'CPA: '.$matchContext['setPiecesPlan'];
        }
        if (($matchContext['watchouts'] ?? '') !== '') {
            $sections[] = 'Vigilance: '.$matchContext['watchouts'];
        }

        if ($sections === []) {
            return null;
        }

        return implode("\n\n", $sections);
    }

    private function buildTeamPreparationInsights(
        Team $team,
        AttendanceRepository $attendanceRepository,
        PlayerMatchStatRepository $playerMatchStatRepository,
    ): array {
        $attendanceSummary = $attendanceRepository->getSummaryByPlayerForTeam($team);
        $players = $team->getPlayers()->toArray();
        $available = [];
        $unavailable = [];
        $topPerformers = [];
        $positionCounts = ['GK' => 0, 'DEF' => 0, 'MID' => 0, 'ATT' => 0];

        foreach ($players as $player) {
            \assert($player instanceof Player);
            $stats = $playerMatchStatRepository->aggregateForPlayer($player);
            $attendanceRate = $attendanceSummary[$player->getId()]['rate'] ?? 0;
            $playerData = [
                'name' => $player->getFullName(),
                'number' => $player->getNumber(),
                'position' => $player->getPosition(),
                'status' => $player->getStatusLabel(),
                'statusRaw' => $player->getStatus(),
                'statusReason' => $player->getStatusReason(),
                'minutes' => $stats['minutes'],
                'avgRating' => $stats['avgRating'],
                'goals' => $stats['goals'],
                'attendanceRate' => $attendanceRate,
            ];

            $bucket = match (true) {
                $player->getPosition() === 'GK' => 'GK',
                \in_array($player->getPosition(), ['CB', 'LB', 'RB'], true) => 'DEF',
                \in_array($player->getPosition(), ['CDM', 'CM', 'CAM'], true) => 'MID',
                default => 'ATT',
            };
            ++$positionCounts[$bucket];

            if ($player->getStatus() === Player::STATUS_PRESENT) {
                $available[] = $playerData;
            } else {
                $unavailable[] = $playerData;
            }

            $score = ($stats['minutes'] ?? 0) + (($stats['avgRating'] ?? 0.0) * 100) + ($attendanceRate * 10) + (($stats['goals'] ?? 0) * 30);
            $topPerformers[] = ['score' => $score, 'player' => $playerData];
        }

        usort($available, fn (array $left, array $right) => [$left['number']] <=> [$right['number']]);
        usort($unavailable, fn (array $left, array $right) => [$left['number']] <=> [$right['number']]);
        usort($topPerformers, fn (array $left, array $right) => [$right['score'], $left['player']['number']] <=> [$left['score'], $right['player']['number']]);

        return [
            'teamName' => $team->getName(),
            'counts' => [
                'players' => \count($players),
                'available' => \count($available),
                'unavailable' => \count($unavailable),
            ],
            'attendanceRate' => $this->calculateTeamAttendanceRate($attendanceSummary),
            'positionCounts' => $positionCounts,
            'recommendedShape' => $this->guessRecommendedShape($positionCounts),
            'available' => $available,
            'unavailable' => array_slice($unavailable, 0, 8),
            'topPerformers' => array_map(static fn (array $entry) => $entry['player'], array_slice($topPerformers, 0, 5)),
        ];
    }

    private function guessRecommendedShape(array $positionCounts): string
    {
        if (($positionCounts['DEF'] ?? 0) >= 7 && ($positionCounts['ATT'] ?? 0) >= 4) {
            return '3-4-3';
        }

        if (($positionCounts['MID'] ?? 0) >= 6) {
            return '4-2-3-1';
        }

        return '4-3-3';
    }

    /**
     * @param array<int, array{total:int,present:int,absent:int,excused:int,late:int,rate:int}> $attendanceSummary
     */
    private function calculateTeamAttendanceRate(array $attendanceSummary): int
    {
        $total = 0;
        $present = 0;
        foreach ($attendanceSummary as $row) {
            $total += (int) ($row['total'] ?? 0);
            $present += (int) ($row['present'] ?? 0);
        }

        if ($total <= 0) {
            return 0;
        }

        return (int) round(($present / $total) * 100);
    }
}
