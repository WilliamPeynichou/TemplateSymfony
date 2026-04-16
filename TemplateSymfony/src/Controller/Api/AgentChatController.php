<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;

#[IsGranted('ROLE_USER')]
#[Route('/api/v1/agent')]
class AgentChatController extends ApiController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(string:AGENT_INTERNAL_SECRET)%')]
        private readonly string $agentInternalSecret,
    ) {}

    #[Route('/chat', name: 'api_agent_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['message'])) {
            return $this->error('Le champ "message" est requis.');
        }

        $payload = [
            'message'         => $data['message'],
            'conversation_id' => $data['conversation_id'] ?? null,
            'team_id'         => $data['team_id'] ?? null,
            'validation_mode' => $data['validation_mode'] ?? 'singular',
            'confirm_action'  => $data['confirm_action'] ?? null,
        ];

        $agentUrl = $_ENV['AGENT_URL'] ?? 'http://agent:8001';
        try {
            $response = $this->httpClient->request('POST', $agentUrl . '/chat', [
                'json'         => $payload,
                'timeout'      => 600,
                'max_duration' => 600,
                'headers'      => [
                    'X-Coach-Id' => $this->getUser()->getId(),
                    'X-Agent-Internal-Secret' => $this->agentInternalSecret,
                ],
            ]);

            try {
                $data = $response->toArray(false);
                return new JsonResponse($data, $response->getStatusCode());
            } catch (DecodingExceptionInterface) {
                return $this->error("L'agent a renvoye une reponse non JSON.", 502);
            }
        } catch (TransportExceptionInterface) {
            return $this->error("L'agent est indisponible ou injoignable.", 502);
        }
    }

    #[Route('/conversations', name: 'api_agent_conversations', methods: ['GET'])]
    public function conversations(Request $request): JsonResponse
    {
        $agentUrl = $_ENV['AGENT_URL'] ?? 'http://agent:8001';
        try {
            $response = $this->httpClient->request('GET', $agentUrl . '/conversations', [
                'headers' => [
                    'X-Coach-Id' => $this->getUser()->getId(),
                    'X-Agent-Internal-Secret' => $this->agentInternalSecret,
                ],
                'query'   => ['team_id' => $request->query->get('team_id')],
            ]);

            try {
                $data = $response->toArray(false);
                return new JsonResponse($data, $response->getStatusCode());
            } catch (DecodingExceptionInterface) {
                return $this->error("L'agent a renvoye une reponse non JSON.", 502);
            }
        } catch (TransportExceptionInterface) {
            return $this->error("L'agent est indisponible ou injoignable.", 502);
        }
    }
}
