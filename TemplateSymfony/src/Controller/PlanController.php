<?php

namespace App\Controller;

use App\Entity\TacticalStrategy;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Redirections 301 pour les anciennes URLs /plans/* vers /strategies/*.
 */
#[IsGranted('ROLE_USER')]
#[Route('/teams/{teamId}/plans')]
class PlanController extends AbstractController
{
    #[Route('', name: 'app_plan_index')]
    public function index(int $teamId): Response
    {
        return $this->redirect(
            $this->generateUrl('app_strategy_index', ['teamId' => $teamId]),
            Response::HTTP_MOVED_PERMANENTLY
        );
    }

    #[Route('/new', name: 'app_plan_new', methods: ['POST'])]
    public function new(int $teamId): Response
    {
        return $this->redirect(
            $this->generateUrl('app_strategy_index', ['teamId' => $teamId]),
            Response::HTTP_MOVED_PERMANENTLY
        );
    }

    #[Route('/{id}/canvas', name: 'app_plan_canvas')]
    public function canvas(int $teamId, int $id, EntityManagerInterface $em): Response
    {
        $team     = $em->find(Team::class, $teamId);
        $strategy = $team
            ? $em->getRepository(TacticalStrategy::class)->findOneBy(['legacyPlanId' => $id, 'team' => $team])
            : null;

        if ($strategy) {
            return $this->redirect(
                $this->generateUrl('app_strategy_edit', ['teamId' => $teamId, 'id' => $strategy->getId()]),
                Response::HTTP_MOVED_PERMANENTLY
            );
        }

        return $this->redirect(
            $this->generateUrl('app_strategy_index', ['teamId' => $teamId]),
            Response::HTTP_MOVED_PERMANENTLY
        );
    }

    #[Route('/{id}/save', name: 'app_plan_save', methods: ['POST'])]
    public function save(int $teamId): JsonResponse
    {
        return new JsonResponse([
            'error'    => 'deprecated',
            'redirect' => $this->generateUrl('app_strategy_index', ['teamId' => $teamId]),
        ], Response::HTTP_GONE);
    }

    #[Route('/{id}/delete', name: 'app_plan_delete', methods: ['POST'])]
    public function delete(int $teamId): Response
    {
        return $this->redirect(
            $this->generateUrl('app_strategy_index', ['teamId' => $teamId]),
            Response::HTTP_MOVED_PERMANENTLY
        );
    }
}
