<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class ApiController extends AbstractController
{
    protected function ok(mixed $data): JsonResponse
    {
        return new JsonResponse(['success' => true, 'data' => $data, 'error' => null]);
    }

    protected function error(string $message, int $status = 400): JsonResponse
    {
        return new JsonResponse(['success' => false, 'data' => null, 'error' => $message], $status);
    }
}
