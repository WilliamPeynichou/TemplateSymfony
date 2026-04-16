<?php

namespace App\Controller\Api;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/v1/teams')]
class TeamApiController extends ApiController
{
    #[Route('', name: 'api_team_list', methods: ['GET'])]
    public function list(TeamRepository $repo): JsonResponse
    {
        /** @var User $coach */
        $coach = $this->getUser();
        $teams = $repo->findByCoach($coach);

        return $this->ok(array_map(fn(Team $t) => $this->serialize($t), $teams));
    }

    #[Route('/{id}', name: 'api_team_get', methods: ['GET'])]
    public function get(Team $team): JsonResponse
    {
        $this->denyAccessUnlessGranted('COACH', $team);
        return $this->ok($this->serialize($team));
    }

    #[Route('', name: 'api_team_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['name'])) {
            return $this->error('Le champ "name" est requis.');
        }

        $team = new Team();
        $team->setName($data['name']);
        $team->setClub($data['club'] ?? null);
        $team->setSeason($data['season'] ?? null);
        $team->setCoach($this->getUser());

        $em->persist($team);
        $em->flush();

        return $this->ok($this->serialize($team));
    }

    #[Route('/{id}', name: 'api_team_update', methods: ['PATCH'])]
    public function update(Team $team, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('COACH', $team);
        $data = json_decode($request->getContent(), true);

        if (isset($data['name']))   $team->setName($data['name']);
        if (array_key_exists('club', $data))   $team->setClub($data['club']);
        if (array_key_exists('season', $data)) $team->setSeason($data['season']);

        $em->flush();
        return $this->ok($this->serialize($team));
    }

    #[Route('/{id}', name: 'api_team_delete', methods: ['DELETE'])]
    public function delete(Team $team, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('COACH', $team);
        $em->remove($team);
        $em->flush();
        return $this->ok(['deleted' => true]);
    }

    private function serialize(Team $t): array
    {
        return [
            'id'         => $t->getId(),
            'name'       => $t->getName(),
            'club'       => $t->getClub(),
            'season'     => $t->getSeason(),
            'createdAt'  => $t->getCreatedAt()->format('c'),
            'playerCount'=> $t->getPlayers()->count(),
        ];
    }
}
