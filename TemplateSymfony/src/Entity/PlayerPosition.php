<?php

namespace App\Entity;

use App\Repository\PlayerPositionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerPositionRepository::class)]
class PlayerPosition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'playerPositions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Composition $composition = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    #[ORM\Column]
    private float $posX = 50.0;

    #[ORM\Column]
    private float $posY = 50.0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $instructions = null;

    public function getId(): ?int { return $this->id; }

    public function getComposition(): ?Composition { return $this->composition; }
    public function setComposition(?Composition $composition): static { $this->composition = $composition; return $this; }

    public function getPlayer(): ?Player { return $this->player; }
    public function setPlayer(?Player $player): static { $this->player = $player; return $this; }

    public function getPosX(): float { return $this->posX; }
    public function setPosX(float $posX): static { $this->posX = $posX; return $this; }

    public function getPosY(): float { return $this->posY; }
    public function setPosY(float $posY): static { $this->posY = $posY; return $this; }

    public function getInstructions(): ?string { return $this->instructions; }
    public function setInstructions(?string $instructions): static { $this->instructions = $instructions; return $this; }
}
