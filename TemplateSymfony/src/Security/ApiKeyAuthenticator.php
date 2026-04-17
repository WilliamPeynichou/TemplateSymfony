<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\ApiKeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authentifie les requêtes vers /api/public/* via header `X-Api-Key: ak_...`.
 */
final class ApiKeyAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ApiKeyRepository $apiKeys,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api/public/')
            && $request->headers->has('X-Api-Key');
    }

    public function authenticate(Request $request): Passport
    {
        $raw = (string) $request->headers->get('X-Api-Key');
        if ('' === $raw) {
            throw new CustomUserMessageAuthenticationException('X-Api-Key header manquant.');
        }

        $hash = hash('sha256', $raw);
        $apiKey = $this->apiKeys->findValidByHash($hash);
        if (null === $apiKey || null === $apiKey->getUser()) {
            throw new CustomUserMessageAuthenticationException('Clé API invalide.');
        }

        $apiKey->touchLastUsed();
        $this->em->flush();

        $userId = (string) $apiKey->getUser()->getId();

        return new SelfValidatingPassport(
            new UserBadge($userId, fn () => $apiKey->getUser()),
        );
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'success' => false,
            'data' => null,
            'error' => $exception->getMessage(),
        ], 401);
    }
}
