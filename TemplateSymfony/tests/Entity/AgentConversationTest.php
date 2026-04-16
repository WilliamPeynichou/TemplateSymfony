<?php

namespace App\Tests\Entity;

use App\Entity\AgentConversation;
use PHPUnit\Framework\TestCase;

final class AgentConversationTest extends TestCase
{
    public function testPendingActionCanBeStoredAndCleared(): void
    {
        $conversation = new AgentConversation();
        $pendingAction = [
            'name' => 'create_player',
            'args' => ['team_id' => 1, 'firstName' => 'Ada'],
        ];

        $conversation->setPendingAction($pendingAction);
        self::assertSame($pendingAction, $conversation->getPendingAction());

        $conversation->clearPendingAction();
        self::assertNull($conversation->getPendingAction());
    }
}
