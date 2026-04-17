<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HealthController extends AbstractController
{
    #[IsGranted('PUBLIC_ACCESS')]
    #[Route('/healthz', name: 'app_healthz', methods: ['GET'])]
    public function healthz(Connection $connection): JsonResponse
    {
        $checks = ['app' => 'ok'];
        $status = 200;

        try {
            $connection->executeQuery('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'ko';
            $status = 503;
        }

        return new JsonResponse($checks, $status);
    }
}
