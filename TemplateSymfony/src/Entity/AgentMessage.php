<?php

namespace App\Entity;

use App\Repository\AgentMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentMessageRepository::class)]
class AgentMessage
{
    public const ROLE_USER      = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_TOOL      = 'tool';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AgentConversation $conversation = null;

    #[ORM\Column(length: 20)]
    private string $role = self::ROLE_USER;

    #[ORM\Column(type: 'text')]
    private string $content = '';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $toolCalls = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getConversation(): ?AgentConversation { return $this->conversation; }
    public function setConversation(?AgentConversation $conversation): static { $this->conversation = $conversation; return $this; }

    public function getRole(): string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getToolCalls(): ?array { return $this->toolCalls; }
    public function setToolCalls(?array $toolCalls): static { $this->toolCalls = $toolCalls; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
