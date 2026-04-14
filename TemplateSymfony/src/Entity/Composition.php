<?php

namespace App\Entity;

use App\Repository\CompositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompositionRepository::class)]
class Composition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'composition')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\Column(length: 150)]
    private string $name = 'Composition principale';

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: PlayerPosition::class, mappedBy: 'composition', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $playerPositions;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->playerPositions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTeam(): ?Team { return $this->team; }
    public function setTeam(?Team $team): static { $this->team = $team; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function getPlayerPositions(): Collection { return $this->playerPositions; }

    public function getPositionForPlayer(Player $player): ?PlayerPosition
    {
        foreach ($this->playerPositions as $pp) {
            if ($pp->getPlayer()->getId() === $player->getId()) {
                return $pp;
            }
        }
        return null;
    }
}
