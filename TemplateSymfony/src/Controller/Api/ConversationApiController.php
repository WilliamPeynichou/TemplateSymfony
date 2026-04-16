<?php

namespace App\Controller\Api;

use App\Entity\AgentConversation;
use App\Entity\AgentMessage;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\AgentConversationRepository;
use App\Repository\AgentMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/v1/conversations')]
class ConversationApiController extends ApiController
{
    #[Route('/{id}', name: 'api_conversation_get', methods: ['GET'])]
    public function getOne(AgentConversation $conv): JsonResponse
    {
        $this->assertConversationOwnership($conv);

        return $this->ok($this->serializeConv($conv));
    }

    #[Route('', name: 'api_conversation_list', methods: ['GET'])]
    public function list(Request $request, AgentConversationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $team = null;
        if ($teamId = $request->query->get('team_id')) {
            $team = $em->find(Team::class, (int) $teamId);
            if (!$team) {
                return $this->error('Equipe introuvable.', 404);
            }
            $this->denyAccessUnlessGranted('COACH', $team);
        }

        $convs = $repo->findByCoach($this->getUser(), $team);

        return $this->ok(array_map(fn(AgentConversation $c) => $this->serializeConv($c), $convs));
    }

    #[Route('', name: 'api_conversation_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $conv = new AgentConversation();
        $conv->setCoach($this->getUser());

        if (!empty($data['team_id'])) {
            $team = $em->find(Team::class, (int) $data['team_id']);
            if (!$team) {
                return $this->error('Equipe introuvable.', 404);
            }
            $this->denyAccessUnlessGranted('COACH', $team);
            $conv->setTeam($team);
        }
        if (!empty($data['title'])) {
            $conv->setTitle($data['title']);
        }

        $em->persist($conv);
        $em->flush();

        return $this->ok($this->serializeConv($conv));
    }

    #[Route('/{id}/messages', name: 'api_conversation_messages', methods: ['GET'])]
    public function messages(AgentConversation $conv, AgentMessageRepository $repo, Request $request): JsonResponse
    {
        $this->assertConversationOwnership($conv);

        $limit = (int) ($request->query->get('limit', 50));
        $msgs  = $repo->findLastByConversation($conv, $limit);

        return $this->ok(array_map(fn(AgentMessage $m) => $this->serializeMsg($m), $msgs));
    }

    #[Route('/{id}/messages', name: 'api_conversation_add_message', methods: ['POST'])]
    public function addMessage(AgentConversation $conv, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertConversationOwnership($conv);

        $data = json_decode($request->getContent(), true);

        if (empty($data['role']) || !isset($data['content'])) {
            return $this->error('Champs "role" et "content" requis.');
        }

        $msg = new AgentMessage();
        $msg->setConversation($conv);
        $msg->setRole($data['role']);
        $msg->setContent($data['content']);
        if (!empty($data['tool_calls'])) {
            $msg->setToolCalls($data['tool_calls']);
        }

        $conv->touch();

        $em->persist($msg);
        $em->flush();

        return $this->ok($this->serializeMsg($msg));
    }

    #[Route('/{id}/title', name: 'api_conversation_update_title', methods: ['PATCH'])]
    public function updateTitle(AgentConversation $conv, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertConversationOwnership($conv);

        $data = json_decode($request->getContent(), true);
        if (!empty($data['title'])) {
            $conv->setTitle($data['title']);
            $conv->touch();
            $em->flush();
        }
        return $this->ok($this->serializeConv($conv));
    }

    #[Route('/{id}/pending-action', name: 'api_conversation_update_pending_action', methods: ['PATCH'])]
    public function updatePendingAction(AgentConversation $conv, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertConversationOwnership($conv);

        $data = json_decode($request->getContent(), true);
        if (array_key_exists('pending_action', $data)) {
            $pendingAction = $data['pending_action'];
            if ($pendingAction !== null && !is_array($pendingAction)) {
                return $this->error('Le champ "pending_action" doit etre un objet JSON ou null.');
            }

            $conv->setPendingAction($pendingAction);
            $conv->touch();
            $em->flush();
        }

        return $this->ok($this->serializeConv($conv));
    }

    private function serializeConv(AgentConversation $c): array
    {
        return [
            'id'           => $c->getId(),
            'title'        => $c->getTitle(),
            'team_id'      => $c->getTeam()?->getId(),
            'message_count'=> $c->getMessages()->count(),
            'pending_action' => $c->getPendingAction(),
            'created_at'   => $c->getCreatedAt()->format('c'),
            'updated_at'   => $c->getUpdatedAt()->format('c'),
        ];
    }

    private function serializeMsg(AgentMessage $m): array
    {
        return [
            'id'         => $m->getId(),
            'role'       => $m->getRole(),
            'content'    => $m->getContent(),
            'tool_calls' => $m->getToolCalls(),
            'created_at' => $m->getCreatedAt()->format('c'),
        ];
    }

    private function assertConversationOwnership(AgentConversation $conv): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($conv->getCoach()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Conversation non autorisee.');
        }
    }
}
