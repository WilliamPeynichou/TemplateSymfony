<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Fixture;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\FixtureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/v1/teams/{teamId}/fixtures')]
class FixtureApiController extends ApiController
{
    #[Route('', name: 'api_fixture_list', methods: ['GET'])]
    public function list(int $teamId, FixtureRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        return $this->ok(array_map(fn (Fixture $f) => $this->serialize($f), $repo->findByTeamOrderedByDate($team)));
    }

    #[Route('/{id}', name: 'api_fixture_get', methods: ['GET'])]
    public function get(int $teamId, Fixture $fixture, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);
        if ($fixture->getTeam()?->getId() !== $team->getId()) {
            throw $this->createAccessDeniedException('Match non autorise pour cette equipe.');
        }

        return $this->ok($this->serialize($fixture));
    }

    #[Route('', name: 'api_fixture_create', methods: ['POST'])]
    public function create(int $teamId, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->error('Payload JSON invalide.');
        }

        foreach (['opponent', 'matchDate'] as $field) {
            if (empty($data[$field])) {
                return $this->error(sprintf('Le champ "%s" est requis.', $field));
            }
        }

        /** @var User $user */
        $user = $this->getUser();

        $fixture = new Fixture();
        $fixture->setTeam($team);
        $fixture->setCoach($user);
        $fixture->setOpponent((string) $data['opponent']);

        try {
            $fixture->setMatchDate(new \DateTimeImmutable((string) $data['matchDate']));
        } catch (\Exception) {
            return $this->error('matchDate invalide (format ISO 8601 attendu).');
        }

        if (isset($data['venue'])) {
            try {
                $fixture->setVenue((string) $data['venue']);
            } catch (\InvalidArgumentException $e) {
                return $this->error($e->getMessage());
            }
        }
        if (\array_key_exists('scoreFor', $data)) {
            $fixture->setScoreFor(null === $data['scoreFor'] ? null : (int) $data['scoreFor']);
        }
        if (\array_key_exists('scoreAgainst', $data)) {
            $fixture->setScoreAgainst(null === $data['scoreAgainst'] ? null : (int) $data['scoreAgainst']);
        }
        if (\array_key_exists('competition', $data)) {
            $fixture->setCompetition($data['competition'] ? (string) $data['competition'] : null);
        }
        if (\array_key_exists('notes', $data)) {
            $fixture->setNotes($data['notes'] ? (string) $data['notes'] : null);
        }
        if (isset($data['status'])) {
            try {
                $fixture->setStatus((string) $data['status']);
            } catch (\InvalidArgumentException $e) {
                return $this->error($e->getMessage());
            }
        }

        $em->persist($fixture);
        $em->flush();

        return $this->ok($this->serialize($fixture));
    }

    #[Route('/{id}', name: 'api_fixture_update', methods: ['PATCH'])]
    public function update(int $teamId, Fixture $fixture, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);
        if ($fixture->getTeam()?->getId() !== $team->getId()) {
            throw $this->createAccessDeniedException('Match non autorise pour cette equipe.');
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->error('Payload JSON invalide.');
        }

        if (isset($data['opponent'])) {
            $fixture->setOpponent((string) $data['opponent']);
        }
        if (isset($data['matchDate'])) {
            try {
                $fixture->setMatchDate(new \DateTimeImmutable((string) $data['matchDate']));
            } catch (\Exception) {
                return $this->error('matchDate invalide.');
            }
        }
        if (isset($data['venue'])) {
            try {
                $fixture->setVenue((string) $data['venue']);
            } catch (\InvalidArgumentException $e) {
                return $this->error($e->getMessage());
            }
        }
        if (\array_key_exists('scoreFor', $data)) {
            $fixture->setScoreFor(null === $data['scoreFor'] ? null : (int) $data['scoreFor']);
        }
        if (\array_key_exists('scoreAgainst', $data)) {
            $fixture->setScoreAgainst(null === $data['scoreAgainst'] ? null : (int) $data['scoreAgainst']);
        }
        if (\array_key_exists('competition', $data)) {
            $fixture->setCompetition($data['competition'] ? (string) $data['competition'] : null);
        }
        if (\array_key_exists('notes', $data)) {
            $fixture->setNotes($data['notes'] ? (string) $data['notes'] : null);
        }
        if (isset($data['status'])) {
            try {
                $fixture->setStatus((string) $data['status']);
            } catch (\InvalidArgumentException $e) {
                return $this->error($e->getMessage());
            }
        }

        $em->flush();

        return $this->ok($this->serialize($fixture));
    }

    #[Route('/{id}', name: 'api_fixture_delete', methods: ['DELETE'])]
    public function delete(int $teamId, Fixture $fixture, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);
        if ($fixture->getTeam()?->getId() !== $team->getId()) {
            throw $this->createAccessDeniedException('Match non autorise pour cette equipe.');
        }

        $em->remove($fixture);
        $em->flush();

        return $this->ok(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Fixture $f): array
    {
        return [
            'id' => $f->getId(),
            'opponent' => $f->getOpponent(),
            'matchDate' => $f->getMatchDate()->format(\DateTimeInterface::ATOM),
            'venue' => $f->getVenue(),
            'scoreFor' => $f->getScoreFor(),
            'scoreAgainst' => $f->getScoreAgainst(),
            'competition' => $f->getCompetition(),
            'notes' => $f->getNotes(),
            'status' => $f->getStatus(),
            'result' => $f->getResult(),
            'createdAt' => $f->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
