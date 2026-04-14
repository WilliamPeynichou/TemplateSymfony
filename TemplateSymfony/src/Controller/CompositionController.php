<?php

namespace App\Controller;

use App\Entity\Composition;
use App\Entity\PlayerPosition;
use App\Entity\Team;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/teams/{id}/composition')]
class CompositionController extends AbstractController
{
    #[Route('', name: 'app_composition_index')]
    public function index(Team $team, PlayerRepository $playerRepository): Response
    {
        $this->denyAccessUnlessGranted('COACH', $team);

        $composition = $team->getComposition();
        $players = $playerRepository->findByTeamOrderedByNumber($team);

        $placedPlayerIds = [];
        $positions = [];
        if ($composition) {
            foreach ($composition->getPlayerPositions() as $pp) {
                $placedPlayerIds[] = $pp->getPlayer()->getId();
                $positions[$pp->getPlayer()->getId()] = [
                    'posX'         => $pp->getPosX(),
                    'posY'         => $pp->getPosY(),
                    'instructions' => $pp->getInstructions(),
                ];
            }
        }

        $benchPlayers = array_filter($players, fn ($p) => !in_array($p->getId(), $placedPlayerIds));

        return $this->render('composition/index.html.twig', [
            'team'          => $team,
            'composition'   => $composition,
            'players'       => $players,
            'benchPlayers'  => array_values($benchPlayers),
            'positions'     => $positions,
        ]);
    }

    #[Route('/save', name: 'app_composition_save', methods: ['POST'])]
    public function save(Team $team, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('COACH', $team);

        $data = json_decode($request->getContent(), true);
        if (!isset($data['positions'])) {
            return $this->json(['error' => 'Invalid data'], 400);
        }

        $composition = $team->getComposition();
        if (!$composition) {
            $composition = new Composition();
            $composition->setTeam($team);
            $team->setComposition($composition);
            $em->persist($composition);
        }

        // Index existing PlayerPositions by player id
        $existingByPlayerId = [];
        foreach ($composition->getPlayerPositions() as $pp) {
            $existingByPlayerId[$pp->getPlayer()->getId()] = $pp;
        }

        $receivedPlayerIds = [];
        foreach ($data['positions'] as $item) {
            $playerId = (int) $item['playerId'];
            $receivedPlayerIds[] = $playerId;

            if (isset($existingByPlayerId[$playerId])) {
                $pp = $existingByPlayerId[$playerId];
            } else {
                $player = $em->find(\App\Entity\Player::class, $playerId);
                if (!$player || $player->getTeam()->getId() !== $team->getId()) continue;

                $pp = new PlayerPosition();
                $pp->setComposition($composition);
                $pp->setPlayer($player);
                $em->persist($pp);
            }

            $pp->setPosX((float) $item['posX']);
            $pp->setPosY((float) $item['posY']);
            if (array_key_exists('instructions', $item)) {
                $pp->setInstructions($item['instructions'] ?: null);
            }
        }

        // Remove players no longer on pitch
        foreach ($existingByPlayerId as $playerId => $pp) {
            if (!in_array($playerId, $receivedPlayerIds)) {
                $em->remove($pp);
            }
        }

        $composition->touch();
        $em->flush();

        return $this->json(['success' => true]);
    }
}
