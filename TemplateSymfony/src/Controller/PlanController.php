<?php

namespace App\Controller;

use App\Entity\Plan;
use App\Entity\PlanNote;
use App\Entity\Team;
use App\Repository\PlanRepository;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/teams/{teamId}/plans')]
class PlanController extends AbstractController
{
    #[Route('', name: 'app_plan_index')]
    public function index(int $teamId, EntityManagerInterface $em, PlanRepository $planRepository): Response
    {
        $team = $em->getRepository(Team::class)->find($teamId);
        if (!$team) throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('COACH', $team);

        $plans = $planRepository->findByTeam($team);

        return $this->render('plan/index.html.twig', [
            'team'  => $team,
            'plans' => $plans,
        ]);
    }

    #[Route('/new', name: 'app_plan_new', methods: ['POST'])]
    public function new(int $teamId, Request $request, EntityManagerInterface $em): Response
    {
        $team = $em->getRepository(Team::class)->find($teamId);
        if (!$team) throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('COACH', $team);

        $plan = new Plan();
        $plan->setTeam($team);
        $plan->setName($request->request->get('name', 'Nouveau plan'));
        $plan->setDescription($request->request->get('description'));

        $em->persist($plan);
        $em->flush();

        return $this->redirectToRoute('app_plan_canvas', ['teamId' => $teamId, 'id' => $plan->getId()]);
    }

    #[Route('/{id}/canvas', name: 'app_plan_canvas')]
    public function canvas(int $teamId, Plan $plan, EntityManagerInterface $em, PlayerRepository $playerRepository): Response
    {
        $team = $em->getRepository(Team::class)->find($teamId);
        if (!$team || $plan->getTeam() !== $team) throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('COACH', $team);

        $players = $playerRepository->findByTeamOrderedByNumber($team);

        // Build notes map: playerId → {posX, posY, note}
        $notes = [];
        foreach ($plan->getNotes() as $planNote) {
            $notes[$planNote->getPlayer()->getId()] = $planNote;
        }

        return $this->render('plan/canvas.html.twig', [
            'team'    => $team,
            'plan'    => $plan,
            'players' => $players,
            'notes'   => $notes,
        ]);
    }

    #[Route('/{id}/save', name: 'app_plan_save', methods: ['POST'])]
    public function save(int $teamId, Plan $plan, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->getRepository(Team::class)->find($teamId);
        if (!$team || $plan->getTeam() !== $team) throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('COACH', $team);

        $data = json_decode($request->getContent(), true);
        if (!isset($data['positions']) || !is_array($data['positions'])) {
            return new JsonResponse(['error' => 'Invalid payload'], 400);
        }

        // Index existing notes
        $existingNotes = [];
        foreach ($plan->getNotes() as $note) {
            $existingNotes[$note->getPlayer()->getId()] = $note;
        }

        $incomingPlayerIds = [];
        foreach ($data['positions'] as $pos) {
            $playerId = (int) $pos['playerId'];
            $incomingPlayerIds[] = $playerId;

            $player = $em->getRepository(\App\Entity\Player::class)->find($playerId);
            if (!$player || $player->getTeam() !== $team) continue;

            if (isset($existingNotes[$playerId])) {
                $note = $existingNotes[$playerId];
            } else {
                $note = new PlanNote();
                $note->setPlan($plan);
                $note->setPlayer($player);
                $em->persist($note);
            }

            $note->setPosX((float) $pos['posX']);
            $note->setPosY((float) $pos['posY']);
            $note->setNote($pos['note'] ?? null);
        }

        // Remove notes no longer in the payload
        foreach ($existingNotes as $pid => $note) {
            if (!in_array($pid, $incomingPlayerIds, true)) {
                $em->remove($note);
            }
        }

        $plan->touch();
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/{id}/delete', name: 'app_plan_delete', methods: ['POST'])]
    public function delete(int $teamId, Plan $plan, Request $request, EntityManagerInterface $em): Response
    {
        $team = $em->getRepository(Team::class)->find($teamId);
        if (!$team || $plan->getTeam() !== $team) throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('COACH', $team);

        if ($this->isCsrfTokenValid('delete_plan_' . $plan->getId(), $request->request->get('_token'))) {
            $em->remove($plan);
            $em->flush();
            $this->addFlash('success', 'Plan supprimé.');
        }

        return $this->redirectToRoute('app_plan_index', ['teamId' => $teamId]);
    }
}
