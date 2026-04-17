<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TrainingSessionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainingSessionRepository::class)]
#[ORM\Table(name: 'training_session')]
class TrainingSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $coach = null;

    #[ORM\Column(length: 150)]
    private string $title = '';

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column]
    private int $durationMinutes = 60;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $focus = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $plan = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->startsAt = new \DateTimeImmutable('+1 day');
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $t): static
    {
        $this->team = $t;

        return $this;
    }

    public function getCoach(): ?User
    {
        return $this->coach;
    }

    public function setCoach(?User $c): static
    {
        $this->coach = $c;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $t): static
    {
        $this->title = $t;

        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $dt): static
    {
        $this->startsAt = $dt;

        return $this;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $m): static
    {
        $this->durationMinutes = max(15, $m);

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $l): static
    {
        $this->location = $l;

        return $this;
    }

    public function getFocus(): ?string
    {
        return $this->focus;
    }

    public function setFocus(?string $f): static
    {
        $this->focus = $f;

        return $this;
    }

    public function getPlan(): ?string
    {
        return $this->plan;
    }

    public function setPlan(?string $p): static
    {
        $this->plan = $p;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->startsAt->modify(sprintf('+%d minutes', $this->durationMinutes));
    }
}
