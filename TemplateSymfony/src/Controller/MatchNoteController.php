<?php

namespace App\Controller;

use App\Entity\MatchNote;
use App\Entity\Team;
use App\Form\MatchNoteType;
use App\Repository\MatchNoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/teams/{teamId}/match-notes')]
class MatchNoteController extends AbstractController
{
    #[Route('', name: 'app_match_note_index')]
    public function index(int $teamId, MatchNoteRepository $repo, EntityManagerInterface $em): Response
    {
        $team = $em->find(Team::class, $teamId);
        $this->denyAccessUnlessGranted('COACH', $team);

        return $this->render('match_notes/index.html.twig', [
            'team'  => $team,
            'notes' => $repo->findByTeamOrderedByDate($team),
        ]);
    }

    #[Route('/new', name: 'app_match_note_new')]
    public function new(int $teamId, Request $request, EntityManagerInterface $em): Response
    {
        $team = $em->find(Team::class, $teamId);
        $this->denyAccessUnlessGranted('COACH', $team);

        $note = new MatchNote();
        $form = $this->createForm(MatchNoteType::class, $note);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $note->setTeam($team);
            $note->setCoach($this->getUser());
            $em->persist($note);
            $em->flush();
            $this->addFlash('success', 'Note post-match enregistrée.');
            return $this->redirectToRoute('app_match_note_index', ['teamId' => $teamId]);
        }

        return $this->render('match_notes/new.html.twig', ['form' => $form, 'team' => $team]);
    }

    #[Route('/{id}/edit', name: 'app_match_note_edit')]
    public function edit(int $teamId, MatchNote $note, Request $request, EntityManagerInterface $em): Response
    {
        $team = $em->find(Team::class, $teamId);
        $this->denyAccessUnlessGranted('COACH', $team);

        $form = $this->createForm(MatchNoteType::class, $note);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Note mise à jour.');
            return $this->redirectToRoute('app_match_note_index', ['teamId' => $teamId]);
        }

        return $this->render('match_notes/edit.html.twig', ['form' => $form, 'team' => $team, 'note' => $note]);
    }

    #[Route('/{id}/delete', name: 'app_match_note_delete', methods: ['POST'])]
    public function delete(int $teamId, MatchNote $note, Request $request, EntityManagerInterface $em): Response
    {
        $team = $em->find(Team::class, $teamId);
        $this->denyAccessUnlessGranted('COACH', $team);

        if ($this->isCsrfTokenValid('delete_match_note_' . $note->getId(), $request->request->get('_token'))) {
            $em->remove($note);
            $em->flush();
            $this->addFlash('success', 'Note supprimée.');
        }

        return $this->redirectToRoute('app_match_note_index', ['teamId' => $teamId]);
    }
}
