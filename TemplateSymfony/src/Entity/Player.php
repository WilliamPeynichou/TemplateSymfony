<?php

namespace App\Entity;

use App\Repository\PlayerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
class Player
{
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

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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
}
