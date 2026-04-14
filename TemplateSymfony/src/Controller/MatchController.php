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

        $scenario = (string) $request->request->get('scenario', 'base');
        $preview = null;

        if ($request->isMethod('POST')) {
            if ($scenario === 'custom_vs_fictional') {
                $selectedTeam = $this->findSelectedTeam($customTeams, (int) $request->request->get('created_team'));

                if (!$selectedTeam) {
                    $this->addFlash('error', 'Sélectionnez une équipe créée pour préparer ce match.');
                } else {
                    $preview = [
                        'scenario' => $scenario,
                        'home' => $this->buildRealTeamPreview($selectedTeam, 'Mon équipe'),
                        'away' => $this->buildFictionalTeamPreview(
                            trim((string) $request->request->get('fictional_name', 'Équipe fictive')),
                            (string) $request->request->get('fictional_players', '')
                        ),
                    ];
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
                    $preview = [
                        'scenario' => 'base',
                        'home' => $this->buildRealTeamPreview($baseTeams[$homeKey], 'Équipe de base'),
                        'away' => $this->buildRealTeamPreview($baseTeams[$awayKey], 'Équipe de base'),
                    ];
                }
            }
        }

        return $this->render('match/prepare.html.twig', [
            'scenario' => $scenario,
            'teams' => $teams,
            'customTeams' => $customTeams,
            'preview' => $preview,
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
     * @return array{name: string, label: string, players: list<array{number: int, name: string, position: string}>}
     */
    private function buildRealTeamPreview(Team $team, string $label): array
    {
        $players = $team->getPlayers()->toArray();
        usort($players, fn (Player $left, Player $right) => $left->getNumber() <=> $right->getNumber());

        return [
            'name' => $team->getName(),
            'label' => $label,
            'players' => array_map(
                fn (Player $player) => [
                    'number' => (int) $player->getNumber(),
                    'name' => $player->getFullName(),
                    'position' => (string) $player->getPosition(),
                ],
                $players
            ),
        ];
    }

    /**
     * @return array{name: string, label: string, players: list<array{number: int, name: string, position: string}>}
     */
    private function buildFictionalTeamPreview(string $name, string $rawPlayers): array
    {
        $players = $this->parseFictionalPlayers($rawPlayers);
        if ($players === []) {
            $players = $this->getDefaultFictionalPlayers();
        }

        usort($players, fn (array $left, array $right) => $left['number'] <=> $right['number']);

        return [
            'name' => $name !== '' ? $name : 'Équipe fictive',
            'label' => 'Équipe fictive',
            'players' => $players,
        ];
    }

    /**
     * @return list<array{number: int, name: string, position: string}>
     */
    private function parseFictionalPlayers(string $rawPlayers): array
    {
        $lines = preg_split('/\R/', $rawPlayers) ?: [];
        $players = [];
        $allowedPositions = array_values(Player::POSITIONS);

        foreach ($lines as $line) {
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

            $players[] = [
                'number' => (int) $parts[0],
                'name' => $parts[1],
                'position' => $position,
            ];
        }

        return $players;
    }

    /**
     * @return list<array{number: int, name: string, position: string}>
     */
    private function getDefaultFictionalPlayers(): array
    {
        return [
            ['number' => 1, 'name' => 'Gardien fictif', 'position' => 'GK'],
            ['number' => 2, 'name' => 'Défenseur droit fictif', 'position' => 'RB'],
            ['number' => 4, 'name' => 'Défenseur central A', 'position' => 'CB'],
            ['number' => 5, 'name' => 'Défenseur central B', 'position' => 'CB'],
            ['number' => 3, 'name' => 'Latéral gauche fictif', 'position' => 'LB'],
            ['number' => 6, 'name' => 'Milieu sentinelle', 'position' => 'CDM'],
            ['number' => 8, 'name' => 'Milieu relayeur', 'position' => 'CM'],
            ['number' => 10, 'name' => 'Milieu créatif', 'position' => 'CAM'],
            ['number' => 7, 'name' => 'Ailier droit fictif', 'position' => 'RW'],
            ['number' => 11, 'name' => 'Ailier gauche fictif', 'position' => 'LW'],
            ['number' => 9, 'name' => 'Buteur fictif', 'position' => 'ST'],
        ];
    }
}
