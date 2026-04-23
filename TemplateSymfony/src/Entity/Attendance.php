<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AttendanceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttendanceRepository::class)]
#[ORM\Table(name: 'attendance')]
class Attendance
{
    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_EXCUSED = 'excused';
    public const STATUS_LATE = 'late';

    public const STATUSES = [
        'Présent' => self::STATUS_PRESENT,
        'Absent' => self::STATUS_ABSENT,
        'Absence justifiée' => self::STATUS_EXCUSED,
        'En retard' => self::STATUS_LATE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Player $player = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Fixture $fixture = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?TrainingSession $trainingSession = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PRESENT;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $recordedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->recordedAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int { return $this->id; }

    public function getPlayer(): ?Player { return $this->player; }
    public function setPlayer(?Player $player): static { $this->player = $player; return $this; }

    public function getFixture(): ?Fixture { return $this->fixture; }
    public function setFixture(?Fixture $fixture): static
    {
        $this->fixture = $fixture;
        if (null !== $fixture) {
            $this->trainingSession = null;
        }

        return $this;
    }

    public function getTrainingSession(): ?TrainingSession { return $this->trainingSession; }
    public function setTrainingSession(?TrainingSession $trainingSession): static
    {
        $this->trainingSession = $trainingSession;
        if (null !== $trainingSession) {
            $this->fixture = null;
        }

        return $this;
    }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static
    {
        if (!\in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Statut de présence invalide: %s', $status));
        }
        $this->status = $status;

        if (self::STATUS_PRESENT === $status || self::STATUS_LATE === $status) {
            $this->reason = null;
        }

        return $this;
    }

    public function getStatusLabel(): string
    {
        return array_search($this->status, self::STATUSES, true) ?: $this->status;
    }

    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason; return $this; }

    public function getRecordedBy(): ?User { return $this->recordedBy; }
    public function setRecordedBy(?User $recordedBy): static { $this->recordedBy = $recordedBy; return $this; }

    public function getRecordedAt(): \DateTimeImmutable { return $this->recordedAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
