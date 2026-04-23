<?php

namespace App\Controller;

use App\Entity\Player;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\AttendanceRepository;
use App\Repository\PlayerMatchStatRepository;
use App\Repository\TeamRepository;
use App\Service\BaseTeamImporter;
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
        BaseTeamImporter $baseTeamImporter,
        AttendanceRepository $attendanceRepository,
        PlayerMatchStatRepository $playerMatchStatRepository,
    ): Response
    {
        /** @var User $coach */
        $coach = $this->getUser();
        $teams = $teamRepository->findByCoach($coach);
        $customTeams = array_values(array_filter(
            $teams,
            fn (Team $team) => !in_array($team->getClub(), ['Liverpool FC', 'Paris Saint-Germain'], true)
        ));

        $scenario = (string) ($request->isMethod('POST')
            ? $request->request->get('scenario', 'base')
            : $request->query->get('scenario', 'base'));
        $selectedCustomTeamId = (int) ($request->isMethod('POST')
            ? $request->request->get('created_team', 0)
            : $request->query->get('created_team', 0));
        $selectedCustomTeam = $this->findSelectedTeam($customTeams, $selectedCustomTeamId);
        $matchContext = $this->buildMatchContext($request);
        $selectedTeamPreview = $selectedCustomTeam instanceof Team
            ? $this->buildTeamPreparationInsights($selectedCustomTeam, $attendanceRepository, $playerMatchStatRepository)
            : null;

        if ($request->isMethod('POST')) {
            if ($scenario === 'custom_vs_fictional') {
                if (!$selectedCustomTeam) {
                    $this->addFlash('error', 'Sélectionnez une équipe créée pour préparer ce match.');
                } else {
                    $request->getSession()->set('match_preparation', [
                        'scenario' => $scenario,
                        'context' => $matchContext,
                        'homeInsights' => $selectedTeamPreview,
                        'home' => $this->buildRealTeamData($selectedCustomTeam, 'home', 'Mon équipe', $attendanceRepository, $playerMatchStatRepository),
                        'away' => $this->buildFictionalTeamData(
                            'away',
                            trim((string) $request->request->get('fictional_name', 'Équipe fictive')),
                            (string) $request->request->get('fictional_players', '')
                        ),
                    ]);

                    return $this->redirectToRoute('app_match_board');
                }
            } else {
                $baseTeams = $baseTeamImporter->ensureBaseTeams($coach);
                $homeKey = (string) $request->request->get('base_home', 'liverpool');
                $awayKey = (string) $request->request->get('base_away', 'paris');

                if (!isset($baseTeams[$homeKey], $baseTeams[$awayKey])) {
                    $this->addFlash('error', 'Sélection invalide pour les équipes de base.');
                } elseif ($homeKey === $awayKey) {
                    $this->addFlash('error', 'Choisissez deux équipes de base différentes.');
                } else {
                    $request->getSession()->set('match_preparation', [
                        'scenario' => 'base',
                        'context' => $matchContext,
                        'homeInsights' => $this->buildTeamPreparationInsights($baseTeams[$homeKey], $attendanceRepository, $playerMatchStatRepository),
                        'awayInsights' => $this->buildTeamPreparationInsights($baseTeams[$awayKey], $attendanceRepository, $playerMatchStatRepository),
                        'home' => $this->buildRealTeamData($baseTeams[$homeKey], 'home', 'Équipe de base', $attendanceRepository, $playerMatchStatRepository),
                        'away' => $this->buildRealTeamData($baseTeams[$awayKey], 'away', 'Équipe de base', $attendanceRepository, $playerMatchStatRepository),
                    ]);

                    return $this->redirectToRoute('app_match_board');
                }
            }
        }

        return $this->render('match/prepare.html.twig', [
            'scenario' => $scenario,
            'customTeams' => $customTeams,
            'selectedCustomTeam' => $selectedCustomTeam,
            'selectedTeamPreview' => $selectedTeamPreview,
            'matchContext' => $matchContext,
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
            'opponentLabel' => trim((string) $request->request->get('opponent_label', $request->query->get('opponent_label', ''))),
            'competition' => trim((string) $request->request->get('competition', $request->query->get('competition', ''))),
            'kickoff' => trim((string) $request->request->get('kickoff', $request->query->get('kickoff', ''))),
            'venue' => trim((string) $request->request->get('venue', $request->query->get('venue', 'home'))),
            'objective' => trim((string) $request->request->get('objective', $request->query->get('objective', ''))),
            'shape' => trim((string) $request->request->get('shape', $request->query->get('shape', '4-3-3'))),
            'pressingPlan' => trim((string) $request->request->get('pressing_plan', $request->query->get('pressing_plan', ''))),
            'inPossessionPlan' => trim((string) $request->request->get('in_possession_plan', $request->query->get('in_possession_plan', ''))),
            'transitionPlan' => trim((string) $request->request->get('transition_plan', $request->query->get('transition_plan', ''))),
            'setPiecesPlan' => trim((string) $request->request->get('set_pieces_plan', $request->query->get('set_pieces_plan', ''))),
            'watchouts' => trim((string) $request->request->get('watchouts', $request->query->get('watchouts', ''))),
        ];
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
}
