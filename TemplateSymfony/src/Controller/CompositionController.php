<?php

namespace App\Controller;

use App\Entity\Composition;
use App\Entity\FormationSlot;
use App\Entity\TacticalStrategy;
use App\Entity\Team;
use App\Repository\TacticalStrategyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/teams/{id}/composition')]
class CompositionController extends AbstractController
{
    #[Route('', name: 'app_composition_index')]
    public function index(
        Team $team,
        EntityManagerInterface $em,
        TacticalStrategyRepository $strategyRepository,
    ): Response
    {
        $this->denyAccessUnlessGranted('COACH', $team);

        $strategy = $this->resolveWorkspaceStrategy($team, $em, $strategyRepository);

        return $this->redirectToRoute('app_strategy_edit', [
            'teamId' => $team->getId(),
            'id' => $strategy->getId(),
        ]);
    }

    #[Route('/save', name: 'app_composition_save', methods: ['POST'])]
    public function save(
        Team $team,
        \Symfony\Component\HttpFoundation\Request $request,
        EntityManagerInterface $em,
        TacticalStrategyRepository $strategyRepository,
    ): JsonResponse
    {
        $this->denyAccessUnlessGranted('COACH', $team);

        $data = json_decode($request->getContent(), true);
        if (!isset($data['positions'])) {
            return $this->json(['error' => 'Invalid data'], 400);
        }

        $strategy = $this->resolveWorkspaceStrategy($team, $em, $strategyRepository);
        $strategy->setMode(TacticalStrategy::MODE_FREE);

        $existingByPlayerId = [];
        foreach ($strategy->getSlots() as $slot) {
            if (!$slot->getPlayer()) {
                $em->remove($slot);
                continue;
            }

            $existingByPlayerId[$slot->getPlayer()->getId()] = $slot;
        }

        $receivedPlayerIds = [];
        $slotIndex = 1;
        foreach ($data['positions'] as $item) {
            $playerId = (int) $item['playerId'];
            $receivedPlayerIds[] = $playerId;

            if (isset($existingByPlayerId[$playerId])) {
                $slot = $existingByPlayerId[$playerId];
            } else {
                $player = $em->find(\App\Entity\Player::class, $playerId);
                if (!$player || $player->getTeam()->getId() !== $team->getId()) {
                    continue;
                }

                $slot = (new FormationSlot())
                    ->setStrategy($strategy)
                    ->setPlayer($player);
                $strategy->getSlots()->add($slot);
                $em->persist($slot);
            }

            $slot
                ->setSlotIndex($slotIndex++)
                ->setPosX((float) $item['posX'])
                ->setPosY((float) $item['posY'])
                ->setLabel($slot->getPlayer()?->getLastName() ?: 'Joueur')
                ->setPositionCode($slot->getPlayer()?->getPosition() ?: 'CM')
                ->setIndividualInstructions($item['instructions'] ?: null);
        }

        foreach ($existingByPlayerId as $playerId => $slot) {
            if (!in_array($playerId, $receivedPlayerIds, true)) {
                $em->remove($slot);
            }
        }

        $strategy->touch();
        $em->flush();

        return $this->json([
            'success' => true,
            'redirect' => $this->generateUrl('app_strategy_edit', [
                'teamId' => $team->getId(),
                'id' => $strategy->getId(),
            ]),
        ]);
    }

    private function resolveWorkspaceStrategy(
        Team $team,
        EntityManagerInterface $em,
        TacticalStrategyRepository $strategyRepository,
    ): TacticalStrategy {
        $strategy = $strategyRepository->findDefaultForTeam($team);
        if ($strategy instanceof TacticalStrategy) {
            return $strategy;
        }

        $teamStrategies = $strategyRepository->findByTeam($team);
        if ($teamStrategies !== []) {
            return $teamStrategies[0];
        }

        $composition = $team->getComposition();
        $strategy = (new TacticalStrategy())
            ->setTeam($team)
            ->setMode(TacticalStrategy::MODE_FREE)
            ->setName($composition instanceof Composition ? $composition->getName() : 'Tactique principale')
            ->setIsDefault(true);

        if ($composition instanceof Composition) {
            $slotIndex = 1;
            foreach ($composition->getPlayerPositions() as $playerPosition) {
                $player = $playerPosition->getPlayer();
                if (!$player) {
                    continue;
                }

                $slot = (new FormationSlot())
                    ->setStrategy($strategy)
                    ->setSlotIndex($slotIndex++)
                    ->setPlayer($player)
                    ->setLabel((string) $player->getLastName())
                    ->setPositionCode((string) $player->getPosition())
                    ->setPosX($playerPosition->getPosX())
                    ->setPosY($playerPosition->getPosY())
                    ->setIndividualInstructions($playerPosition->getInstructions());
                $strategy->getSlots()->add($slot);
                $em->persist($slot);
            }
        }

        $em->persist($strategy);
        $em->flush();

        return $strategy;
    }
}
