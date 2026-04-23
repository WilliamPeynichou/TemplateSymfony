<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlayerStatusHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerStatusHistoryRepository::class)]
#[ORM\Table(name: 'player_status_history')]
class PlayerStatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Player $player = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $changedBy = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $oldStatus = null;

    #[ORM\Column(length: 20)]
    private string $newStatus = Player::STATUS_PRESENT;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column]
    private \DateTimeImmutable $changedAt;

    public function __construct()
    {
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getPlayer(): ?Player { return $this->player; }
    public function setPlayer(?Player $player): static { $this->player = $player; return $this; }

    public function getChangedBy(): ?User { return $this->changedBy; }
    public function setChangedBy(?User $changedBy): static { $this->changedBy = $changedBy; return $this; }

    public function getOldStatus(): ?string { return $this->oldStatus; }
    public function setOldStatus(?string $oldStatus): static { $this->oldStatus = $oldStatus; return $this; }

    public function getNewStatus(): string { return $this->newStatus; }
    public function setNewStatus(string $newStatus): static
    {
        if (!\in_array($newStatus, Player::STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Statut joueur invalide: %s', $newStatus));
        }
        $this->newStatus = $newStatus;

        return $this;
    }

    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason; return $this; }

    public function getChangedAt(): \DateTimeImmutable { return $this->changedAt; }

    public function getOldStatusLabel(): string
    {
        return $this->oldStatus ? (array_search($this->oldStatus, Player::STATUSES, true) ?: $this->oldStatus) : 'Création';
    }

    public function getNewStatusLabel(): string
    {
        return array_search($this->newStatus, Player::STATUSES, true) ?: $this->newStatus;
    }
}
