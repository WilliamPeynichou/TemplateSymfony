<?php

namespace App\Controller;

use App\Entity\Team;
use App\Form\TeamType;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/teams')]
class TeamController extends AbstractController
{
    #[Route('', name: 'app_team_index')]
    public function index(TeamRepository $teamRepository): Response
    {
        $teams = $teamRepository->findByCoach($this->getUser());
        return $this->render('team/index.html.twig', ['teams' => $teams]);
    }

    #[Route('/new', name: 'app_team_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $team = new Team();
        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $team->setCoach($this->getUser());
            $em->persist($team);
            $em->flush();
            $this->addFlash('success', 'Équipe créée avec succès.');
            return $this->redirectToRoute('app_team_index');
        }

        return $this->render('team/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'app_team_edit')]
    public function edit(Team $team, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('COACH', $team);

        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Équipe mise à jour.');
            return $this->redirectToRoute('app_team_index');
        }

        return $this->render('team/edit.html.twig', ['form' => $form, 'team' => $team]);
    }

    #[Route('/{id}/delete', name: 'app_team_delete', methods: ['POST'])]
    public function delete(Team $team, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('COACH', $team);

        if ($this->isCsrfTokenValid('delete_team_' . $team->getId(), $request->request->get('_token'))) {
            $em->remove($team);
            $em->flush();
            $this->addFlash('success', 'Équipe supprimée.');
        }

        return $this->redirectToRoute('app_team_index');
    }
}
