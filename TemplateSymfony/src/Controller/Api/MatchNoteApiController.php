<?php

namespace App\Controller\Api;

use App\Entity\MatchNote;
use App\Entity\Team;
use App\Repository\MatchNoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/v1/teams/{teamId}/match-notes')]
class MatchNoteApiController extends ApiController
{
    #[Route('', name: 'api_match_note_list', methods: ['GET'])]
    public function list(int $teamId, MatchNoteRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        return $this->ok(array_map(fn(MatchNote $n) => $this->serialize($n), $repo->findByTeamOrderedByDate($team)));
    }

    #[Route('/{id}', name: 'api_match_note_get', methods: ['GET'])]
    public function get(int $teamId, MatchNote $note, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);
        if ($note->getTeam()?->getId() !== $team->getId()) {
            throw $this->createAccessDeniedException('Note non autorisee pour cette equipe.');
        }
        return $this->ok($this->serialize($note));
    }

    #[Route('', name: 'api_match_note_create', methods: ['POST'])]
    public function create(int $teamId, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        $data = json_decode($request->getContent(), true);
        foreach (['matchLabel', 'content'] as $field) {
            if (empty($data[$field])) return $this->error("Le champ \"$field\" est requis.");
        }

        $note = new MatchNote();
        $note->setTeam($team);
        $note->setCoach($this->getUser());
        $note->setMatchLabel($data['matchLabel']);
        $note->setContent($data['content']);
        if (!empty($data['matchDate'])) {
            $note->setMatchDate(new \DateTimeImmutable($data['matchDate']));
        }

        $em->persist($note);
        $em->flush();

        return $this->ok($this->serialize($note));
    }

    #[Route('/{id}', name: 'api_match_note_update', methods: ['PATCH'])]
    public function update(int $teamId, MatchNote $note, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);
        if ($note->getTeam()?->getId() !== $team->getId()) {
            throw $this->createAccessDeniedException('Note non autorisee pour cette equipe.');
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['matchLabel'])) $note->setMatchLabel($data['matchLabel']);
        if (isset($data['content']))    $note->setContent($data['content']);
        if (isset($data['matchDate']))  $note->setMatchDate(new \DateTimeImmutable($data['matchDate']));

        $em->flush();
        return $this->ok($this->serialize($note));
    }

    #[Route('/{id}', name: 'api_match_note_delete', methods: ['DELETE'])]
    public function delete(int $teamId, MatchNote $note, EntityManagerInterface $em): JsonResponse
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            return $this->error('Equipe introuvable.', 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);
        if ($note->getTeam()?->getId() !== $team->getId()) {
            throw $this->createAccessDeniedException('Note non autorisee pour cette equipe.');
        }

        $em->remove($note);
        $em->flush();
        return $this->ok(['deleted' => true]);
    }

    private function serialize(MatchNote $n): array
    {
        return [
            'id'         => $n->getId(),
            'matchLabel' => $n->getMatchLabel(),
            'content'    => $n->getContent(),
            'matchDate'  => $n->getMatchDate()->format('Y-m-d'),
            'createdAt'  => $n->getCreatedAt()->format('c'),
        ];
    }
}
