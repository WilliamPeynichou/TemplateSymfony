<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Fixture;
use App\Entity\Player;
use App\Entity\PlayerMatchStat;
use App\Entity\Team;
use App\Repository\PlayerMatchStatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/v1/teams/{teamId}/players/{playerId}/stats')]
class PlayerMatchStatApiController extends ApiController
{
    #[Route('', name: 'api_player_stat_list', methods: ['GET'])]
    public function list(int $teamId, int $playerId, EntityManagerInterface $em, PlayerMatchStatRepository $repo): JsonResponse
    {
        [$team, $player] = $this->loadScope($em, $teamId, $playerId);

        $stats = $repo->findBy(['player' => $player], ['id' => 'DESC']);

        return $this->ok([
            'items' => array_map([$this, 'serialize'], $stats),
            'aggregate' => $repo->aggregateForPlayer($player),
        ]);
    }

    #[Route('', name: 'api_player_stat_create', methods: ['POST'])]
    public function create(int $teamId, int $playerId, Request $request, EntityManagerInterface $em, PlayerMatchStatRepository $repo): JsonResponse
    {
        [$team, $player] = $this->loadScope($em, $teamId, $playerId);

        $data = json_decode($request->getContent(), true) ?? [];
        $fixtureId = (int) ($data['fixture_id'] ?? 0);
        if ($fixtureId <= 0) {
            return $this->error('fixture_id requis.', 422);
        }
        $fixture = $em->find(Fixture::class, $fixtureId);
        if (!$fixture || $fixture->getTeam()?->getId() !== $team->getId()) {
            return $this->error('Match introuvable pour cette équipe.', 404);
        }

        $stat = $repo->findOneByPlayerAndFixture($player, $fixture) ?? new PlayerMatchStat();
        $stat->setPlayer($player)->setFixture($fixture);
        $this->applyPayload($stat, $data);

        $em->persist($stat);
        $em->flush();

        return $this->ok($this->serialize($stat), 201);
    }

    #[Route('/{id}', name: 'api_player_stat_update', methods: ['PATCH'])]
    public function update(int $teamId, int $playerId, PlayerMatchStat $stat, Request $request, EntityManagerInterface $em): JsonResponse
    {
        [$team, $player] = $this->loadScope($em, $teamId, $playerId);
        if ($stat->getPlayer()?->getId() !== $player->getId()) {
            throw $this->createAccessDeniedException('Stat non autorisée.');
        }

        $this->applyPayload($stat, json_decode($request->getContent(), true) ?? []);
        $em->flush();

        return $this->ok($this->serialize($stat));
    }

    #[Route('/{id}', name: 'api_player_stat_delete', methods: ['DELETE'])]
    public function delete(int $teamId, int $playerId, PlayerMatchStat $stat, EntityManagerInterface $em): JsonResponse
    {
        [$team, $player] = $this->loadScope($em, $teamId, $playerId);
        if ($stat->getPlayer()?->getId() !== $player->getId()) {
            throw $this->createAccessDeniedException('Stat non autorisée.');
        }

        $em->remove($stat);
        $em->flush();

        return $this->ok(['deleted' => true]);
    }

    /**
     * @return array{0: Team, 1: Player}
     */
    private function loadScope(EntityManagerInterface $em, int $teamId, int $playerId): array
    {
        $team = $em->find(Team::class, $teamId);
        if (!$team) {
            throw $this->createNotFoundException('Équipe introuvable.');
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        $player = $em->find(Player::class, $playerId);
        if (!$player || $player->getTeam()?->getId() !== $team->getId()) {
            throw $this->createNotFoundException('Joueur introuvable pour cette équipe.');
        }

        return [$team, $player];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyPayload(PlayerMatchStat $stat, array $data): void
    {
        if (isset($data['minutes_played'])) {
            $stat->setMinutesPlayed((int) $data['minutes_played']);
        }
        if (isset($data['goals'])) {
            $stat->setGoals((int) $data['goals']);
        }
        if (isset($data['assists'])) {
            $stat->setAssists((int) $data['assists']);
        }
        if (isset($data['yellow_cards'])) {
            $stat->setYellowCards((int) $data['yellow_cards']);
        }
        if (isset($data['red_cards'])) {
            $stat->setRedCards((int) $data['red_cards']);
        }
        if (\array_key_exists('rating', $data)) {
            $stat->setRating(null !== $data['rating'] ? (string) $data['rating'] : null);
        }
        if (\array_key_exists('notes', $data)) {
            $stat->setNotes($data['notes'] ? (string) $data['notes'] : null);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PlayerMatchStat $s): array
    {
        return [
            'id' => $s->getId(),
            'fixture_id' => $s->getFixture()?->getId(),
            'fixture_label' => $s->getFixture() ? sprintf(
                '%s vs %s',
                $s->getFixture()->getMatchDate()->format('Y-m-d'),
                $s->getFixture()->getOpponent(),
            ) : null,
            'minutes_played' => $s->getMinutesPlayed(),
            'goals' => $s->getGoals(),
            'assists' => $s->getAssists(),
            'yellow_cards' => $s->getYellowCards(),
            'red_cards' => $s->getRedCards(),
            'rating' => $s->getRating(),
            'notes' => $s->getNotes(),
            'created_at' => $s->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
