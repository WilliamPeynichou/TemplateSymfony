<?php

namespace App\Controller\Api;

use App\Entity\Player;
use App\Entity\Team;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/v1/teams/{teamId}/players')]
class PlayerApiController extends ApiController
{
    #[Route('', name: 'api_player_list', methods: ['GET'])]
    public function list(int $teamId, PlayerRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        return $this->ok(array_map(fn(Player $p) => $this->serialize($p), $repo->findByTeamOrderedByNumber($team)));
    }

    #[Route('/{id}', name: 'api_player_get', methods: ['GET'])]
    public function get(int $teamId, Player $player, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);
        if ($player->getTeam()?->getId() !== $team->getId()) {
            throw $this->createAccessDeniedException('Joueur non autorise pour cette equipe.');
        }
        return $this->ok($this->serialize($player));
    }

    #[Route('', name: 'api_player_create', methods: ['POST'])]
    public function create(int $teamId, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        $data = json_decode($request->getContent(), true);
        foreach (['firstName', 'lastName', 'number', 'position'] as $field) {
            if (!isset($data[$field])) return $this->error("Le champ \"$field\" est requis.");
        }

        $player = new Player();
        $player->setFirstName($data['firstName']);
        $player->setLastName($data['lastName']);
        $player->setNumber((int) $data['number']);
        $player->setPosition($data['position']);
        $player->setStrongFoot($data['strongFoot'] ?? null);
        $player->setHeight(isset($data['height']) ? (int) $data['height'] : null);
        $player->setWeight(isset($data['weight']) ? (int) $data['weight'] : null);
        $player->setTeam($team);

        $em->persist($player);
        $em->flush();

        return $this->ok($this->serialize($player));
    }

    #[Route('/{id}', name: 'api_player_update', methods: ['PATCH'])]
    public function update(int $teamId, Player $player, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);
        if ($player->getTeam()?->getId() !== $team->getId()) {
            throw $this->createAccessDeniedException('Joueur non autorise pour cette equipe.');
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['firstName']))  $player->setFirstName($data['firstName']);
        if (isset($data['lastName']))   $player->setLastName($data['lastName']);
        if (isset($data['number']))     $player->setNumber((int) $data['number']);
        if (isset($data['position']))   $player->setPosition($data['position']);
        if (array_key_exists('strongFoot', $data)) $player->setStrongFoot($data['strongFoot']);
        if (array_key_exists('height', $data))     $player->setHeight($data['height'] ? (int) $data['height'] : null);
        if (array_key_exists('weight', $data))     $player->setWeight($data['weight'] ? (int) $data['weight'] : null);

        $em->flush();
        return $this->ok($this->serialize($player));
    }

    #[Route('/{id}', name: 'api_player_delete', methods: ['DELETE'])]
    public function delete(int $teamId, Player $player, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);
        if ($player->getTeam()?->getId() !== $team->getId()) {
            throw $this->createAccessDeniedException('Joueur non autorise pour cette equipe.');
        }

        $em->remove($player);
        $em->flush();
        return $this->ok(['deleted' => true]);
    }

    private function serialize(Player $p): array
    {
        return [
            'id'         => $p->getId(),
            'firstName'  => $p->getFirstName(),
            'lastName'   => $p->getLastName(),
            'fullName'   => $p->getFullName(),
            'number'     => $p->getNumber(),
            'position'   => $p->getPosition(),
            'strongFoot' => $p->getStrongFoot(),
            'height'     => $p->getHeight(),
            'weight'     => $p->getWeight(),
            'age'        => $p->getAge(),
            'photo'      => $p->getPhoto(),
        ];
    }
}
