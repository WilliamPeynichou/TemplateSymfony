<?php

namespace App\Controller;

use App\Entity\Player;
use App\Entity\Team;
use App\Form\PlayerType;
use App\Repository\PlayerRepository;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/teams/{teamId}/players')]
class PlayerController extends AbstractController
{
    #[Route('', name: 'app_player_index')]
    public function index(int $teamId, PlayerRepository $playerRepository, EntityManagerInterface $em): Response
    {
        $team = $em->find(Team::class, $teamId);
        $this->denyAccessUnlessGranted('COACH', $team);

        $players = $playerRepository->findByTeamOrderedByNumber($team);
        return $this->render('player/index.html.twig', ['team' => $team, 'players' => $players]);
    }

    #[Route('/new', name: 'app_player_new')]
    public function new(int $teamId, Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        $team = $em->find(Team::class, $teamId);
        $this->denyAccessUnlessGranted('COACH', $team);

        $player = new Player();
        $form = $this->createForm(PlayerType::class, $player);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photoFile = $form->get('photoFile')->getData();
            if ($photoFile) {
                $player->setPhoto($uploader->upload($photoFile));
            }
            $player->setTeam($team);
            $em->persist($player);
            $em->flush();
            $this->addFlash('success', $player->getFullName() . ' ajouté à l\'effectif.');
            return $this->redirectToRoute('app_player_index', ['teamId' => $teamId]);
        }

        return $this->render('player/new.html.twig', ['form' => $form, 'team' => $team]);
    }

    #[Route('/{id}/edit', name: 'app_player_edit')]
    public function edit(int $teamId, Player $player, Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        $team = $em->find(Team::class, $teamId);
        $this->denyAccessUnlessGranted('COACH', $team);

        $form = $this->createForm(PlayerType::class, $player);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photoFile = $form->get('photoFile')->getData();
            if ($photoFile) {
                if ($player->getPhoto()) {
                    $uploader->remove($player->getPhoto());
                }
                $player->setPhoto($uploader->upload($photoFile));
            }
            $em->flush();
            $this->addFlash('success', 'Joueur mis à jour.');
            return $this->redirectToRoute('app_player_index', ['teamId' => $teamId]);
        }

        return $this->render('player/edit.html.twig', ['form' => $form, 'team' => $team, 'player' => $player]);
    }

    #[Route('/{id}/delete', name: 'app_player_delete', methods: ['POST'])]
    public function delete(int $teamId, Player $player, Request $request, EntityManagerInterface $em, FileUploadService $uploader): Response
    {
        $team = $em->find(Team::class, $teamId);
        $this->denyAccessUnlessGranted('COACH', $team);

        if ($this->isCsrfTokenValid('delete_player_' . $player->getId(), $request->request->get('_token'))) {
            if ($player->getPhoto()) {
                $uploader->remove($player->getPhoto());
            }
            $em->remove($player);
            $em->flush();
            $this->addFlash('success', 'Joueur supprimé.');
        }

        return $this->redirectToRoute('app_player_index', ['teamId' => $teamId]);
    }
}
