<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlayerMatchStatRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Performances d'un joueur sur un match précis.
 */
#[ORM\Entity(repositoryClass: PlayerMatchStatRepository::class)]
#[ORM\Table(name: 'player_match_stat')]
#[ORM\UniqueConstraint(name: 'UNIQ_PLAYER_FIXTURE', columns: ['player_id', 'fixture_id'])]
class PlayerMatchStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Fixture $fixture = null;

    #[ORM\Column]
    private int $minutesPlayed = 0;

    #[ORM\Column]
    private int $goals = 0;

    #[ORM\Column]
    private int $assists = 0;

    #[ORM\Column]
    private int $yellowCards = 0;

    #[ORM\Column]
    private int $redCards = 0;

    #[ORM\Column(type: 'decimal', precision: 3, scale: 1, nullable: true)]
    private ?string $rating = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(?Player $p): static
    {
        $this->player = $p;

        return $this;
    }

    public function getFixture(): ?Fixture
    {
        return $this->fixture;
    }

    public function setFixture(?Fixture $f): static
    {
        $this->fixture = $f;

        return $this;
    }

    public function getMinutesPlayed(): int
    {
        return $this->minutesPlayed;
    }

    public function setMinutesPlayed(int $m): static
    {
        $this->minutesPlayed = max(0, $m);

        return $this;
    }

    public function getGoals(): int
    {
        return $this->goals;
    }

    public function setGoals(int $g): static
    {
        $this->goals = max(0, $g);

        return $this;
    }

    public function getAssists(): int
    {
        return $this->assists;
    }

    public function setAssists(int $a): static
    {
        $this->assists = max(0, $a);

        return $this;
    }

    public function getYellowCards(): int
    {
        return $this->yellowCards;
    }

    public function setYellowCards(int $y): static
    {
        $this->yellowCards = max(0, $y);

        return $this;
    }

    public function getRedCards(): int
    {
        return $this->redCards;
    }

    public function setRedCards(int $r): static
    {
        $this->redCards = max(0, $r);

        return $this;
    }

    public function getRating(): ?string
    {
        return $this->rating;
    }

    public function setRating(?string $r): static
    {
        if (null !== $r) {
            $n = (float) $r;
            if ($n < 0 || $n > 10) {
                throw new \InvalidArgumentException('La note doit être entre 0 et 10.');
            }
        }
        $this->rating = $r;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $n): static
    {
        $this->notes = $n;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
