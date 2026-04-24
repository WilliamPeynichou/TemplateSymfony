<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CallupPlayerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CallupPlayerRepository::class)]
#[ORM\Table(name: 'callup_player')]
#[ORM\UniqueConstraint(name: 'uniq_callup_player', columns: ['callup_id', 'player_id'])]
class CallupPlayer
{
    public const ROLE_STARTER    = 'starter';
    public const ROLE_SUBSTITUTE = 'substitute';
    public const ROLE_NOT_CALLED = 'not_called';
    public const ROLE_ABSENT     = 'absent';

    public const ROLES = [
        self::ROLE_STARTER,
        self::ROLE_SUBSTITUTE,
        self::ROLE_NOT_CALLED,
        self::ROLE_ABSENT,
    ];

    public const REASON_INJURY     = 'injury';
    public const REASON_SUSPENSION = 'suspension';
    public const REASON_CHOICE     = 'choice';
    public const REASON_SCHOOL     = 'school';
    public const REASON_DISCIPLINE = 'discipline';
    public const REASON_FAMILY     = 'family';
    public const REASON_OTHER      = 'other';

    public const REASONS = [
        self::REASON_INJURY,
        self::REASON_SUSPENSION,
        self::REASON_CHOICE,
        self::REASON_SCHOOL,
        self::REASON_DISCIPLINE,
        self::REASON_FAMILY,
        self::REASON_OTHER,
    ];

    public const REASON_LABELS = [
        self::REASON_INJURY     => 'Blessure',
        self::REASON_SUSPENSION => 'Suspension',
        self::REASON_CHOICE     => 'Choix coach',
        self::REASON_SCHOOL     => 'École / études',
        self::REASON_DISCIPLINE => 'Discipline',
        self::REASON_FAMILY     => 'Raison familiale',
        self::REASON_OTHER      => 'Autre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'callupPlayers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Callup $callup = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Player $player = null;

    #[ORM\Column(length: 20)]
    private string $role = self::ROLE_NOT_CALLED;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(nullable: true)]
    private ?int $jerseyNumber = null;

    public function getId(): ?int { return $this->id; }

    public function getCallup(): ?Callup { return $this->callup; }

    public function setCallup(?Callup $callup): static
    {
        $this->callup = $callup;
        return $this;
    }

    public function getPlayer(): ?Player { return $this->player; }

    public function setPlayer(?Player $player): static
    {
        $this->player = $player;
        return $this;
    }

    public function getRole(): string { return $this->role; }

    public function setRole(string $role): static
    {
        if (!\in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException('Rôle de convocation invalide : ' . $role);
        }
        $this->role = $role;
        return $this;
    }

    public function getReason(): ?string { return $this->reason; }

    public function setReason(?string $reason): static
    {
        if ($reason !== null && !\in_array($reason, self::REASONS, true)) {
            throw new \InvalidArgumentException('Raison invalide : ' . $reason);
        }
        $this->reason = $reason;
        return $this;
    }

    public function getReasonLabel(): string
    {
        return self::REASON_LABELS[$this->reason] ?? '';
    }

    public function getNotes(): ?string { return $this->notes; }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getJerseyNumber(): ?int { return $this->jerseyNumber; }

    public function setJerseyNumber(?int $jerseyNumber): static
    {
        $this->jerseyNumber = $jerseyNumber;
        return $this;
    }

    public function isStarter(): bool    { return $this->role === self::ROLE_STARTER; }
    public function isSubstitute(): bool { return $this->role === self::ROLE_SUBSTITUTE; }
}
