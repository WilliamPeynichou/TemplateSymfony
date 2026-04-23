<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\FormationSlot;
use App\Entity\Player;
use App\Entity\TacticalStrategy;
use App\Entity\Team;
use App\Repository\PlayerRepository;
use App\Repository\TacticalStrategyRepository;
use App\Service\FormationLibrary;
use App\Service\RoleLibrary;
use App\Service\SquadAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des stratégies tactiques avancées (FM-style).
 */
#[IsGranted('ROLE_USER')]
#[Route('/teams/{teamId}/strategies')]
class StrategyController extends AbstractController
{
    #[Route('', name: 'app_strategy_index')]
    public function index(
        int $teamId,
        EntityManagerInterface $em,
        TacticalStrategyRepository $strategyRepo,
    ): Response {
        $team = $em->find(Team::class, $teamId);
        if (!$team) throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('COACH', $team);

        return $this->render('strategy/index.html.twig', [
            'team'       => $team,
            'strategies' => $strategyRepo->findByTeam($team),
            'usage'      => $strategyRepo->getFormationUsageStats($team),
            'formations' => FormationLibrary::all(),
        ]);
    }

    #[Route('/new', name: 'app_strategy_new', methods: ['POST'])]
    public function new(
        int $teamId,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $team = $em->find(Team::class, $teamId);
        if (!$team) throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('COACH', $team);

        $name = trim((string) $request->request->get('name', ''));
        $formationKey = (string) $request->request->get('formation', '4-3-3');

        if ($name === '') $name = 'Stratégie ' . (new \DateTimeImmutable())->format('d/m/Y');
        if (!\in_array($formationKey, FormationLibrary::keys(), true)) {
            $formationKey = '4-3-3';
        }

        $strategy = (new TacticalStrategy())
            ->setTeam($team)
            ->setName($name)
            ->setFormation($formationKey);

        // Hydrate default slots from FormationLibrary
        foreach (FormationLibrary::get($formationKey)['slots'] as $slotData) {
            $slot = (new FormationSlot())
                ->setStrategy($strategy)
                ->setSlotIndex($slotData['index'])
                ->setPositionCode($slotData['code'])
                ->setLabel($slotData['label'])
                ->setRole($slotData['role'])
                ->setDuty($slotData['duty'])
                ->setPosX($slotData['x'])
                ->setPosY($slotData['y']);
            $strategy->getSlots()->add($slot);
            $em->persist($slot);
        }

        $em->persist($strategy);
        $em->flush();

        return $this->redirectToRoute('app_strategy_edit', [
            'teamId' => $teamId,
            'id'     => $strategy->getId(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_strategy_edit', requirements: ['id' => '\d+'])]
    public function edit(
        int $teamId,
        int $id,
        EntityManagerInterface $em,
        PlayerRepository $playerRepository,
        SquadAnalyzer $analyzer,
    ): Response {
        $team = $em->find(Team::class, $teamId);
        $strategy = $em->find(TacticalStrategy::class, $id);
        if (!$team || !$strategy || $strategy->getTeam()?->getId() !== $team->getId()) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        $players = $playerRepository->findByTeamOrderedByNumber($team);
        $suggestions = $analyzer->suggestBestEleven($team, $strategy);

        return $this->render('strategy/edit.html.twig', [
            'team'         => $team,
            'strategy'     => $strategy,
            'players'      => $players,
            'formations'   => FormationLibrary::all(),
            'roles'        => RoleLibrary::all(),
            'suggestions'  => $suggestions,
        ]);
    }

    #[Route('/{id}/save', name: 'app_strategy_save', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function save(
        int $teamId,
        int $id,
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $team = $em->find(Team::class, $teamId);
        $strategy = $em->find(TacticalStrategy::class, $id);
        if (!$team || !$strategy || $strategy->getTeam()?->getId() !== $team->getId()) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) return new JsonResponse(['error' => 'invalid'], 400);

        // Team-level settings
        foreach ([
            'name', 'description', 'formation', 'mentality', 'pressingIntensity',
            'defensiveLine', 'buildUpStyle', 'width', 'tempo', 'attackingFocus',
            'inPossessionNotes', 'outOfPossessionNotes', 'transitionNotes', 'setPieceNotes',
        ] as $field) {
            if (!array_key_exists($field, $data)) continue;
            $value = $data[$field];
            $setter = 'set' . ucfirst($field);
            try {
                $strategy->$setter($value);
            } catch (\InvalidArgumentException) {
                // Ignore invalid enum values silently (client should validate).
            }
        }

        // Slots: replace-in-place (by slotIndex)
        if (isset($data['slots']) && is_array($data['slots'])) {
            $existingByIndex = [];
            foreach ($strategy->getSlots() as $slot) {
                $existingByIndex[$slot->getSlotIndex()] = $slot;
            }

            $seen = [];
            foreach ($data['slots'] as $slotData) {
                if (!isset($slotData['slotIndex'])) continue;
                $idx = (int) $slotData['slotIndex'];
                $seen[$idx] = true;

                $slot = $existingByIndex[$idx] ?? null;
                if (!$slot) {
                    $slot = (new FormationSlot())
                        ->setStrategy($strategy)
                        ->setSlotIndex($idx);
                    $strategy->getSlots()->add($slot);
                    $em->persist($slot);
                }

                if (isset($slotData['positionCode'])) $slot->setPositionCode((string) $slotData['positionCode']);
                if (isset($slotData['label']))        $slot->setLabel((string) $slotData['label']);
                if (isset($slotData['role']))         $slot->setRole((string) $slotData['role']);
                if (isset($slotData['duty']))         {
                    try { $slot->setDuty((string) $slotData['duty']); } catch (\InvalidArgumentException) {}
                }
                if (isset($slotData['posX'])) $slot->setPosX((float) $slotData['posX']);
                if (isset($slotData['posY'])) $slot->setPosY((float) $slotData['posY']);
                if (array_key_exists('individualInstructions', $slotData)) {
                    $slot->setIndividualInstructions($slotData['individualInstructions'] ?: null);
                }

                if (array_key_exists('playerId', $slotData)) {
                    $playerId = $slotData['playerId'];
                    if ($playerId === null || $playerId === '') {
                        $slot->setPlayer(null);
                    } else {
                        $player = $em->find(Player::class, (int) $playerId);
                        if ($player && $player->getTeam()?->getId() === $team->getId()) {
                            $slot->setPlayer($player);
                        }
                    }
                }
            }

            // Remove slots not present anymore (when client shrinks formation)
            foreach ($existingByIndex as $idx => $slot) {
                if (!isset($seen[$idx])) {
                    $em->remove($slot);
                }
            }
        }

        $strategy->touch();
        $em->flush();

        return new JsonResponse([
            'ok'        => true,
            'updatedAt' => $strategy->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/{id}/apply-formation', name: 'app_strategy_apply_formation', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function applyFormation(
        int $teamId,
        int $id,
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $team = $em->find(Team::class, $teamId);
        $strategy = $em->find(TacticalStrategy::class, $id);
        if (!$team || !$strategy || $strategy->getTeam()?->getId() !== $team->getId()) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        $data = json_decode($request->getContent(), true);
        $key = (string) ($data['formation'] ?? '');
        if (!\in_array($key, FormationLibrary::keys(), true)) {
            return new JsonResponse(['error' => 'invalid_formation'], 400);
        }

        // Conserver les joueurs existants par slotIndex si possible
        $existingPlayers = [];
        foreach ($strategy->getSlots() as $slot) {
            $existingPlayers[$slot->getSlotIndex()] = $slot->getPlayer();
            $em->remove($slot);
        }
        $strategy->getSlots()->clear();
        $em->flush();

        foreach (FormationLibrary::get($key)['slots'] as $slotData) {
            $slot = (new FormationSlot())
                ->setStrategy($strategy)
                ->setSlotIndex($slotData['index'])
                ->setPositionCode($slotData['code'])
                ->setLabel($slotData['label'])
                ->setRole($slotData['role'])
                ->setDuty($slotData['duty'])
                ->setPosX($slotData['x'])
                ->setPosY($slotData['y'])
                ->setPlayer($existingPlayers[$slotData['index']] ?? null);
            $strategy->getSlots()->add($slot);
            $em->persist($slot);
        }

        $strategy->setFormation($key)->touch();
        $em->flush();

        return new JsonResponse(['ok' => true, 'redirect' => $this->generateUrl('app_strategy_edit', ['teamId' => $teamId, 'id' => $id])]);
    }

    #[Route('/{id}/set-default', name: 'app_strategy_set_default', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function setDefault(
        int $teamId,
        int $id,
        Request $request,
        EntityManagerInterface $em,
        TacticalStrategyRepository $strategyRepo,
    ): Response {
        $team = $em->find(Team::class, $teamId);
        $strategy = $em->find(TacticalStrategy::class, $id);
        if (!$team || !$strategy || $strategy->getTeam()?->getId() !== $team->getId()) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        if (!$this->isCsrfTokenValid('default_strategy_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        foreach ($strategyRepo->findByTeam($team) as $s) {
            $s->setIsDefault($s->getId() === $strategy->getId());
        }
        $em->flush();

        $this->addFlash('success', 'Stratégie définie par défaut.');
        return $this->redirectToRoute('app_strategy_index', ['teamId' => $teamId]);
    }

    #[Route('/{id}/clone', name: 'app_strategy_clone', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function clone(
        int $teamId,
        int $id,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $team = $em->find(Team::class, $teamId);
        $src = $em->find(TacticalStrategy::class, $id);
        if (!$team || !$src || $src->getTeam()?->getId() !== $team->getId()) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        if (!$this->isCsrfTokenValid('clone_strategy_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $copy = (new TacticalStrategy())
            ->setTeam($team)
            ->setName($src->getName().' (copie)')
            ->setDescription($src->getDescription())
            ->setFormation($src->getFormation())
            ->setMentality($src->getMentality())
            ->setPressingIntensity($src->getPressingIntensity())
            ->setDefensiveLine($src->getDefensiveLine())
            ->setBuildUpStyle($src->getBuildUpStyle())
            ->setWidth($src->getWidth())
            ->setTempo($src->getTempo())
            ->setAttackingFocus($src->getAttackingFocus())
            ->setInPossessionNotes($src->getInPossessionNotes())
            ->setOutOfPossessionNotes($src->getOutOfPossessionNotes())
            ->setTransitionNotes($src->getTransitionNotes())
            ->setSetPieceNotes($src->getSetPieceNotes());

        foreach ($src->getSlots() as $slot) {
            $copySlot = (new FormationSlot())
                ->setStrategy($copy)
                ->setSlotIndex($slot->getSlotIndex())
                ->setPositionCode($slot->getPositionCode())
                ->setLabel($slot->getLabel())
                ->setRole($slot->getRole())
                ->setDuty($slot->getDuty())
                ->setPosX($slot->getPosX())
                ->setPosY($slot->getPosY())
                ->setPlayer($slot->getPlayer())
                ->setIndividualInstructions($slot->getIndividualInstructions());
            $copy->getSlots()->add($copySlot);
            $em->persist($copySlot);
        }
        $em->persist($copy);
        $em->flush();

        return $this->redirectToRoute('app_strategy_edit', ['teamId' => $teamId, 'id' => $copy->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_strategy_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        int $teamId,
        int $id,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $team = $em->find(Team::class, $teamId);
        $strategy = $em->find(TacticalStrategy::class, $id);
        if (!$team || !$strategy || $strategy->getTeam()?->getId() !== $team->getId()) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        if (!$this->isCsrfTokenValid('delete_strategy_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($strategy);
        $em->flush();
        $this->addFlash('success', 'Stratégie supprimée.');

        return $this->redirectToRoute('app_strategy_index', ['teamId' => $teamId]);
    }
}
