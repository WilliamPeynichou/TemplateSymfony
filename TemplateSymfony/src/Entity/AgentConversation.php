<?php

namespace App\Entity;

use App\Repository\AgentConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentConversationRepository::class)]
class AgentConversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $coach = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Team $team = null;

    #[ORM\Column(length: 200)]
    private string $title = 'Nouvelle conversation';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $pendingAction = null;

    #[ORM\OneToMany(targetEntity: AgentMessage::class, mappedBy: 'conversation', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->messages  = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getCoach(): ?User { return $this->coach; }
    public function setCoach(?User $coach): static { $this->coach = $coach; return $this; }

    public function getTeam(): ?Team { return $this->team; }
    public function setTeam(?Team $team): static { $this->team = $team; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
    public function getPendingAction(): ?array { return $this->pendingAction; }
    public function setPendingAction(?array $pendingAction): static { $this->pendingAction = $pendingAction; return $this; }
    public function clearPendingAction(): static { $this->pendingAction = null; return $this; }

    public function getMessages(): Collection { return $this->messages; }
}
