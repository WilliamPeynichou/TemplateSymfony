<?php

namespace App\Entity;

use App\Repository\PlayerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
#[Assert\Callback('validateStatusReason')]
class Player
{
    public const STATUS_PRESENT = 'present';
    public const STATUS_INJURED = 'injured';
    public const STATUS_ABSENT = 'absent';

    public const STATUSES = [
        'Présent' => self::STATUS_PRESENT,
        'Blessé' => self::STATUS_INJURED,
        'Absent' => self::STATUS_ABSENT,
    ];

    public const POSITIONS = [
        'Gardien'          => 'GK',
        'Défenseur central' => 'CB',
        'Latéral gauche'   => 'LB',
        'Latéral droit'    => 'RB',
        'Milieu défensif'  => 'CDM',
        'Milieu central'   => 'CM',
        'Milieu offensif'  => 'CAM',
        'Ailier gauche'    => 'LW',
        'Ailier droit'     => 'RW',
        'Avant-centre'     => 'ST',
    ];

    public const STRONG_FEET = [
        'Pied droit'  => 'right',
        'Pied gauche' => 'left',
        'Les deux'    => 'both',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column]
    private ?int $number = null;

    #[ORM\Column(length: 10)]
    private ?string $position = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PRESENT;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $statusReason = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $emergencyContact = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateOfBirth = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $strongFoot = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[ORM\Column(nullable: true)]
    private ?int $weight = null;

    #[ORM\ManyToOne(inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\OneToOne(mappedBy: 'player', targetEntity: PlayerAttributes::class, cascade: ['persist', 'remove'])]
    private ?PlayerAttributes $attributes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getAttributes(): ?PlayerAttributes { return $this->attributes; }

    public function setAttributes(?PlayerAttributes $attributes): static
    {
        if ($attributes !== null && $attributes->getPlayer() !== $this) {
            $attributes->setPlayer($this);
        }
        $this->attributes = $attributes;
        return $this;
    }

    public function getPositionGroup(): string
    {
        return match ($this->position) {
            'GK' => 'GK',
            'CB', 'LB', 'RB' => 'DEF',
            'CDM', 'CM', 'CAM' => 'MID',
            'LW', 'RW', 'ST' => 'ATT',
            default => 'MID',
        };
    }

    public function getId(): ?int { return $this->id; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $firstName): static { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(string $lastName): static { $this->lastName = $lastName; return $this; }

    public function getFullName(): string { return $this->firstName . ' ' . $this->lastName; }

    public function getNumber(): ?int { return $this->number; }
    public function setNumber(int $number): static { $this->number = $number; return $this; }

    public function getPosition(): ?string { return $this->position; }
    public function setPosition(string $position): static { $this->position = $position; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static
    {
        if (!\in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Statut joueur invalide: %s', $status));
        }
        $this->status = $status;

        if (self::STATUS_PRESENT === $status) {
            $this->statusReason = null;
        }

        return $this;
    }

    public function getStatusLabel(): string
    {
        return array_search($this->status, self::STATUSES, true) ?: $this->status;
    }

    public function getStatusReason(): ?string { return $this->statusReason; }
    public function setStatusReason(?string $statusReason): static { $this->statusReason = $statusReason; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }

    public function getEmergencyContact(): ?string { return $this->emergencyContact; }
    public function setEmergencyContact(?string $emergencyContact): static { $this->emergencyContact = $emergencyContact; return $this; }

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $photo): static { $this->photo = $photo; return $this; }

    public function getDateOfBirth(): ?\DateTimeImmutable { return $this->dateOfBirth; }
    public function setDateOfBirth(?\DateTimeImmutable $dateOfBirth): static { $this->dateOfBirth = $dateOfBirth; return $this; }

    public function getStrongFoot(): ?string { return $this->strongFoot; }
    public function setStrongFoot(?string $strongFoot): static { $this->strongFoot = $strongFoot; return $this; }

    public function getHeight(): ?int { return $this->height; }
    public function setHeight(?int $height): static { $this->height = $height; return $this; }

    public function getWeight(): ?int { return $this->weight; }
    public function setWeight(?int $weight): static { $this->weight = $weight; return $this; }

    public function getTeam(): ?Team { return $this->team; }
    public function setTeam(?Team $team): static { $this->team = $team; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getAge(): ?int
    {
        if (!$this->dateOfBirth) return null;
        return $this->dateOfBirth->diff(new \DateTimeImmutable())->y;
    }

    public function validateStatusReason(ExecutionContextInterface $context): void
    {
        if (\in_array($this->status, [self::STATUS_INJURED, self::STATUS_ABSENT], true) && !$this->statusReason) {
            $context
                ->buildViolation('Renseignez un motif pour un joueur blessé ou absent.')
                ->atPath('statusReason')
                ->addViolation();
        }
    }
}
