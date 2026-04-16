<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class AgentTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly string $agentSecret,
    ) {}

    public function supports(Request $request): ?bool
    {
        $auth = $request->headers->get('Authorization', '');
        return str_starts_with($auth, 'Bearer agent:') && $request->headers->has('X-Coach-Id');
    }

    public function authenticate(Request $request): Passport
    {
        $token    = substr($request->headers->get('Authorization'), strlen('Bearer agent:'));
        $coachId  = (int) $request->headers->get('X-Coach-Id');

        if ($token !== $this->agentSecret) {
            throw new CustomUserMessageAuthenticationException('Token agent invalide.');
        }

        return new SelfValidatingPassport(
            new UserBadge((string) $coachId, fn(string $id) => $this->userRepository->find((int) $id))
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // continuer la requête
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['success' => false, 'data' => null, 'error' => $exception->getMessageKey()], 401);
    }
}
