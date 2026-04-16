<?php

namespace App\Entity;

use App\Repository\MatchNoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatchNoteRepository::class)]
class MatchNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'matchNotes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $coach = null;

    #[ORM\Column(length: 150)]
    private string $matchLabel = '';

    #[ORM\Column(type: 'text')]
    private string $content = '';

    #[ORM\Column]
    private \DateTimeImmutable $matchDate;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->matchDate = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTeam(): ?Team { return $this->team; }
    public function setTeam(?Team $team): static { $this->team = $team; return $this; }

    public function getCoach(): ?User { return $this->coach; }
    public function setCoach(?User $coach): static { $this->coach = $coach; return $this; }

    public function getMatchLabel(): string { return $this->matchLabel; }
    public function setMatchLabel(string $matchLabel): static { $this->matchLabel = $matchLabel; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getMatchDate(): \DateTimeImmutable { return $this->matchDate; }
    public function setMatchDate(\DateTimeImmutable $matchDate): static { $this->matchDate = $matchDate; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
