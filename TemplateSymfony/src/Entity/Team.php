<?php

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $club = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $season = null;

    #[ORM\ManyToOne(inversedBy: 'teams')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $coach = null;

    #[ORM\OneToMany(targetEntity: Player::class, mappedBy: 'team', orphanRemoval: true)]
    private Collection $players;

    #[ORM\OneToOne(targetEntity: Composition::class, mappedBy: 'team', cascade: ['persist', 'remove'])]
    private ?Composition $composition = null;

    #[ORM\OneToMany(targetEntity: Plan::class, mappedBy: 'team', orphanRemoval: true, cascade: ['remove'])]
    private Collection $plans;

    #[ORM\OneToMany(targetEntity: MatchNote::class, mappedBy: 'team', orphanRemoval: true, cascade: ['remove'])]
    private Collection $matchNotes;

    #[ORM\OneToMany(targetEntity: TacticalStrategy::class, mappedBy: 'team', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $strategies;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->players    = new ArrayCollection();
        $this->plans      = new ArrayCollection();
        $this->matchNotes = new ArrayCollection();
        $this->strategies = new ArrayCollection();
        $this->createdAt  = new \DateTimeImmutable();
    }

    public function getStrategies(): Collection { return $this->strategies; }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getClub(): ?string { return $this->club; }
    public function setClub(?string $club): static { $this->club = $club; return $this; }

    public function getSeason(): ?string { return $this->season; }
    public function setSeason(?string $season): static { $this->season = $season; return $this; }

    public function getCoach(): ?User { return $this->coach; }
    public function setCoach(?User $coach): static { $this->coach = $coach; return $this; }

    public function getPlayers(): Collection { return $this->players; }

    public function getComposition(): ?Composition { return $this->composition; }
    public function setComposition(?Composition $composition): static { $this->composition = $composition; return $this; }

    public function getPlans(): Collection { return $this->plans; }

    public function getMatchNotes(): Collection { return $this->matchNotes; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
