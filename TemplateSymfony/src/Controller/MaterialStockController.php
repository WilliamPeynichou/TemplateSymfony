<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MaterialStock;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\MaterialStockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/teams/{id}/material')]
class MaterialStockController extends AbstractController
{
    #[Route('', name: 'app_team_material', methods: ['GET'])]
    public function index(
        Team $team,
        MaterialStockRepository $materialStockRepository,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        /** @var User $user */
        $user = $this->getUser();

        $byTeamId = [];
        foreach ($materialStockRepository->aggregateTotalsByTeamForCoach($user) as $row) {
            $byTeamId[(int) $row['teamId']] = $row;
        }

        $clubBreakdown = [];
        foreach ($user->getTeams() as $coachTeam) {
            $tid               = (int) $coachTeam->getId();
            $clubBreakdown[]   = [
                'team'  => $coachTeam,
                'qty'   => $byTeamId[$tid]['qty'] ?? 0,
                'lines' => $byTeamId[$tid]['lines'] ?? 0,
            ];
        }
        usort($clubBreakdown, function (array $a, array $b): int {
            return strcasecmp($a['team']->getName() ?? '', $b['team']->getName() ?? '');
        });

        return $this->render('material/index.html.twig', [
            'team'            => $team,
            'teamLines'       => $materialStockRepository->findForTeam($team),
            'teamQtySum'      => $materialStockRepository->sumQuantityForTeam($team),
            'clubStockTotal'  => $materialStockRepository->sumQuantityAllTeamsForCoach($user),
            'clubLineCount'   => $materialStockRepository->countLinesAllTeamsForCoach($user),
            'clubBreakdown'   => $clubBreakdown,
        ]);
    }

    #[Route('/new', name: 'app_team_material_new', methods: ['POST'])]
    public function new(
        Team $team,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        if (!$this->isCsrfTokenValid('material_stock_new_'.$team->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        /** @var User $user */
        $user = $this->getUser();

        $line = $this->hydrateFromRequest(new MaterialStock(), $request);
        $line->setCoach($user);
        $line->setTeam($team);

        $em->persist($line);
        $em->flush();
        $this->addFlash('success', 'Ligne de stock ajoutée.');

        return $this->redirectToRoute('app_team_material', ['id' => $team->getId()]);
    }

    #[Route('/{lineId}/edit', name: 'app_team_material_edit', requirements: ['lineId' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Team $team,
        int $lineId,
        Request $request,
        EntityManagerInterface $em,
        MaterialStockRepository $materialStockRepository,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        $line = $materialStockRepository->find($lineId);
        if (!$line instanceof MaterialStock || !$this->canAccessLine($line, $team)) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('material_stock_edit_'.$line->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $this->hydrateFromRequest($line, $request);
            $line->setTeam($team);
            $line->touch();
            $em->flush();
            $this->addFlash('success', 'Ligne mise à jour.');

            return $this->redirectToRoute('app_team_material', ['id' => $team->getId()]);
        }

        return $this->render('material/edit.html.twig', [
            'team' => $team,
            'line' => $line,
        ]);
    }

    #[Route('/{lineId}/delete', name: 'app_team_material_delete', requirements: ['lineId' => '\d+'], methods: ['POST'])]
    public function delete(
        Team $team,
        int $lineId,
        Request $request,
        EntityManagerInterface $em,
        MaterialStockRepository $materialStockRepository,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        $line = $materialStockRepository->find($lineId);
        if (!$line instanceof MaterialStock || !$this->canAccessLine($line, $team)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('material_stock_delete_'.$line->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $em->remove($line);
        $em->flush();
        $this->addFlash('success', 'Ligne supprimée.');

        return $this->redirectToRoute('app_team_material', ['id' => $team->getId()]);
    }

    private function canAccessLine(MaterialStock $line, Team $team): bool
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($line->getCoach()?->getId() !== $user->getId()) {
            return false;
        }

        return $line->getTeam()?->getId() === $team->getId();
    }

    private function hydrateFromRequest(MaterialStock $line, Request $request): MaterialStock
    {
        $label = trim((string) $request->request->get('label', ''));
        if ($label === '') {
            $label = 'Sans nom';
        }

        $quantity = (int) $request->request->get('quantity', 0);
        if ($quantity < 0) {
            $quantity = 0;
        }

        $unit      = trim((string) $request->request->get('unit', ''));
        $notes     = trim((string) $request->request->get('notes', ''));
        $sortOrder = (int) $request->request->get('sort_order', 0);

        return $line
            ->setLabel($label)
            ->setQuantity($quantity)
            ->setUnit('' !== $unit ? $unit : null)
            ->setNotes('' !== $notes ? $notes : null)
            ->setSortOrder($sortOrder);
    }
}
