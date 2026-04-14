<?php

namespace App\Controller;

use App\Entity\Player;
use App\Entity\Team;
use App\Entity\User;
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
    public function prepare(Request $request, TeamRepository $teamRepository, BaseTeamImporter $baseTeamImporter): Response
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

        if ($request->isMethod('POST')) {
            if ($scenario === 'custom_vs_fictional') {
                $selectedTeam = $this->findSelectedTeam($customTeams, (int) $request->request->get('created_team'));

                if (!$selectedTeam) {
                    $this->addFlash('error', 'Sélectionnez une équipe créée pour préparer ce match.');
                } else {
                    $request->getSession()->set('match_preparation', [
                        'scenario' => $scenario,
                        'home' => $this->buildRealTeamData($selectedTeam, 'home', 'Mon équipe'),
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
                        'home' => $this->buildRealTeamData($baseTeams[$homeKey], 'home', 'Équipe de base'),
                        'away' => $this->buildRealTeamData($baseTeams[$awayKey], 'away', 'Équipe de base'),
                    ]);

                    return $this->redirectToRoute('app_match_board');
                }
            }
        }

        return $this->render('match/prepare.html.twig', [
            'scenario' => $scenario,
            'customTeams' => $customTeams,
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
     *   players: list<array{id: string, name: string, shortName: string, number: int, position: string, side: string, photo: ?string}>
     * }
     */
    private function buildRealTeamData(Team $team, string $side, string $label): array
    {
        $players = $team->getPlayers()->toArray();
        usort($players, fn (Player $left, Player $right) => $left->getNumber() <=> $right->getNumber());

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
}
