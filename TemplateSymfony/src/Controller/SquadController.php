<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Player;
use App\Entity\PlayerAttributes;
use App\Entity\Team;
use App\Repository\PlayerRepository;
use App\Service\RoleLibrary;
use App\Service\SquadAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Vue Football Manager de l'effectif : attributs, condition, moral, suitability rôles.
 */
#[IsGranted('ROLE_USER')]
#[Route('/teams/{teamId}/squad')]
class SquadController extends AbstractController
{
    #[Route('', name: 'app_squad_index')]
    public function index(
        int $teamId,
        PlayerRepository $playerRepository,
        SquadAnalyzer $analyzer,
        EntityManagerInterface $em,
    ): Response {
        $team = $em->find(Team::class, $teamId);
        if (!$team) throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('COACH', $team);

        $players = $playerRepository->findByTeamOrderedByNumber($team);
        $summary = $analyzer->analyzeSquad($players);

        return $this->render('squad/index.html.twig', [
            'team'    => $team,
            'players' => $players,
            'summary' => $summary,
        ]);
    }

    #[Route('/{playerId}', name: 'app_squad_player', requirements: ['playerId' => '\d+'])]
    public function player(
        int $teamId,
        int $playerId,
        EntityManagerInterface $em,
    ): Response {
        $team = $em->find(Team::class, $teamId);
        $player = $em->find(Player::class, $playerId);

        if (!$team || !$player || $player->getTeam()?->getId() !== $team->getId()) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        $attrs = $player->getAttributes();
        if (!$attrs) {
            $attrs = (new PlayerAttributes())->setPlayer($player);
            $player->setAttributes($attrs);
            $em->persist($attrs);
            $em->flush();
        }

        // Calcule suitability pour tous les rôles de son groupe de poste
        $suitabilities = [];
        foreach (RoleLibrary::byGroup($player->getPositionGroup()) as $key => $role) {
            $suitabilities[$key] = [
                'label'       => $role['label'],
                'suitability' => RoleLibrary::suitability($attrs, $key),
            ];
        }
        uasort($suitabilities, fn ($a, $b) => $b['suitability'] <=> $a['suitability']);

        return $this->render('squad/player.html.twig', [
            'team'          => $team,
            'player'        => $player,
            'attrs'         => $attrs,
            'suitabilities' => $suitabilities,
        ]);
    }

    #[Route('/{playerId}/attributes', name: 'app_squad_player_attributes_save', methods: ['POST'], requirements: ['playerId' => '\d+'])]
    public function saveAttributes(
        int $teamId,
        int $playerId,
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $team = $em->find(Team::class, $teamId);
        $player = $em->find(Player::class, $playerId);
        if (!$team || !$player || $player->getTeam()?->getId() !== $team->getId()) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }
        $this->denyAccessUnlessGranted('COACH', $team);

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) return new JsonResponse(['error' => 'invalid'], 400);

        $attrs = $player->getAttributes() ?? (new PlayerAttributes())->setPlayer($player);

        $allAttributeKeys = array_merge(
            array_keys(PlayerAttributes::TECHNICAL_ATTRIBUTES),
            array_keys(PlayerAttributes::MENTAL_ATTRIBUTES),
            array_keys(PlayerAttributes::PHYSICAL_ATTRIBUTES),
            array_keys(PlayerAttributes::GOALKEEPING_ATTRIBUTES),
        );

        foreach ($allAttributeKeys as $key) {
            if (isset($payload[$key])) {
                $attrs->set($key, (int) $payload[$key]);
            }
        }

        if (isset($payload['condition'])) $attrs->setCondition((int) $payload['condition']);
        if (isset($payload['morale']))    $attrs->setMorale((string) $payload['morale']);
        if (isset($payload['potentialAbility'])) $attrs->setPotentialAbility((int) $payload['potentialAbility']);

        $attrs->touch();
        if (!$attrs->getId()) {
            $player->setAttributes($attrs);
            $em->persist($attrs);
        }
        $em->flush();

        return new JsonResponse([
            'ok'               => true,
            'currentAbility'   => $attrs->getCurrentAbility($player->getPosition() ?? 'CM'),
            'updatedAt'        => $attrs->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
