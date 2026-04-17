<?php

declare(strict_types=1);

namespace App\Controller\Api\Public;

use App\Entity\User;
use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API publique — documentée via OpenAPI (voir /api/doc).
 *
 * Authentification : X-Api-Key.
 */
#[Route('/api/public')]
class PublicTeamsController extends AbstractController
{
    #[Route('/teams', name: 'api_public_teams', methods: ['GET'])]
    public function list(TeamRepository $teams): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $items = [];
        foreach ($teams->findBy(['coach' => $user]) as $t) {
            $items[] = [
                'id' => $t->getId(),
                'name' => $t->getName(),
                'category' => $t->getCategory(),
            ];
        }

        return new JsonResponse(['success' => true, 'data' => $items, 'error' => null]);
    }
}
