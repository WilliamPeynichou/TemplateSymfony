<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MatchNote;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Envoie les notes de match vers l'agent FastAPI pour indexation vectorielle.
 *
 * Ce service est appelé depuis MatchNoteApiController après create/update/delete.
 * L'indexation est non bloquante côté UX : si l'agent est down, on log et on
 * continue (les erreurs sont reparables via un reindex manuel).
 */
final class AgentRagIndexer
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(string:AGENT_INTERNAL_SECRET)%')]
        private readonly string $agentInternalSecret,
        #[Autowire('%env(default::AGENT_BASE_URL)%')]
        private readonly ?string $agentBaseUrl = null,
    ) {
    }

    public function indexMatchNote(MatchNote $note): void
    {
        $coach = $note->getCoach();
        $team = $note->getTeam();
        if (null === $coach || null === $team) {
            return;
        }

        $payload = [
            'source_type' => 'match_note',
            'note' => [
                'id' => $note->getId(),
                'content' => $note->getContent(),
                'matchLabel' => $note->getMatchLabel(),
                'matchDate' => $note->getMatchDate()->format('Y-m-d'),
                'team_id' => $team->getId(),
            ],
        ];

        $this->call('POST', '/rag/index', (int) $coach->getId(), $payload);
    }

    public function deleteMatchNote(int $coachId, int $noteId): void
    {
        // L'API /rag/delete n'existe pas encore : on la câblera dans une prochaine
        // itération. En attendant, les chunks orphelins ne gênent pas les
        // recherches (ils resteront liés à un source_id sans note côté Symfony).
        $this->logger->info('[RAG] Delete hook disabled (TODO: expose /rag/delete)', [
            'coach_id' => $coachId,
            'note_id' => $noteId,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function call(string $method, string $path, int $coachId, array $payload): void
    {
        $baseUrl = $this->agentBaseUrl ?: 'http://agent:8001';

        try {
            $response = $this->httpClient->request($method, $baseUrl.$path, [
                'json' => $payload,
                'timeout' => 15,
                'headers' => [
                    'X-Coach-Id' => (string) $coachId,
                    'X-Agent-Internal-Secret' => $this->agentInternalSecret,
                ],
            ]);
            $status = $response->getStatusCode();
            if ($status >= 400) {
                $this->logger->warning('[RAG] indexer returned non-2xx', [
                    'path' => $path,
                    'status' => $status,
                ]);
            }
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('[RAG] agent unreachable, skipping indexing', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
