<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FixtureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FixtureRepository::class)]
#[ORM\Table(name: 'fixture')]
class Fixture
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PLAYED = 'played';
    public const STATUS_CANCELLED = 'cancelled';

    public const HOME = 'home';
    public const AWAY = 'away';
    public const NEUTRAL = 'neutral';

    public const COMPETITION_FRIENDLY = 'amical';
    public const COMPETITION_LEAGUE = 'championnat';
    public const COMPETITION_CUP = 'coupe';

    public const STATUSES = [self::STATUS_SCHEDULED, self::STATUS_PLAYED, self::STATUS_CANCELLED];
    public const VENUES = [self::HOME, self::AWAY, self::NEUTRAL];
    public const COMPETITIONS = [self::COMPETITION_FRIENDLY, self::COMPETITION_LEAGUE, self::COMPETITION_CUP];

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
    private string $opponent = '';

    #[ORM\Column]
    private \DateTimeImmutable $matchDate;

    #[ORM\Column(length: 10)]
    private string $venue = self::HOME;

    #[ORM\Column(nullable: true)]
    private ?int $scoreFor = null;

    #[ORM\Column(nullable: true)]
    private ?int $scoreAgainst = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $competition = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TacticalStrategy $tacticalStrategy = null;

    #[ORM\OneToOne(mappedBy: 'fixture', targetEntity: Callup::class, cascade: ['persist', 'remove'])]
    private ?Callup $callup = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_SCHEDULED;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->matchDate = new \DateTimeImmutable();
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

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getCoach(): ?User
    {
        return $this->coach;
    }

    public function setCoach(?User $coach): static
    {
        $this->coach = $coach;

        return $this;
    }

    public function getOpponent(): string
    {
        return $this->opponent;
    }

    public function setOpponent(string $opponent): static
    {
        $this->opponent = $opponent;

        return $this;
    }

    public function getMatchDate(): \DateTimeImmutable
    {
        return $this->matchDate;
    }

    public function setMatchDate(\DateTimeImmutable $matchDate): static
    {
        $this->matchDate = $matchDate;

        return $this;
    }

    public function getVenue(): string
    {
        return $this->venue;
    }

    public function setVenue(string $venue): static
    {
        if (!\in_array($venue, self::VENUES, true)) {
            throw new \InvalidArgumentException(sprintf('Venue invalide: %s', $venue));
        }
        $this->venue = $venue;

        return $this;
    }

    public function getScoreFor(): ?int
    {
        return $this->scoreFor;
    }

    public function setScoreFor(?int $scoreFor): static
    {
        $this->scoreFor = $scoreFor;

        return $this;
    }

    public function getScoreAgainst(): ?int
    {
        return $this->scoreAgainst;
    }

    public function setScoreAgainst(?int $scoreAgainst): static
    {
        $this->scoreAgainst = $scoreAgainst;

        return $this;
    }

    public function getCompetition(): ?string
    {
        return $this->competition;
    }

    public function setCompetition(?string $competition): static
    {
        $this->competition = $competition;

        return $this;
    }

    public function getTacticalStrategy(): ?TacticalStrategy
    {
        return $this->tacticalStrategy;
    }

    public function setTacticalStrategy(?TacticalStrategy $tacticalStrategy): static
    {
        $this->tacticalStrategy = $tacticalStrategy;

        return $this;
    }

    public function getCallup(): ?Callup { return $this->callup; }

    public function setCallup(?Callup $callup): static
    {
        if ($callup === null && $this->callup !== null) {
            $this->callup->setFixture(null);
        }
        if ($callup !== null && $callup->getFixture() !== $this) {
            $callup->setFixture($this);
        }
        $this->callup = $callup;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!\in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Statut invalide: %s', $status));
        }
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResult(): ?string
    {
        if (null === $this->scoreFor || null === $this->scoreAgainst) {
            return null;
        }

        return match (true) {
            $this->scoreFor > $this->scoreAgainst => 'win',
            $this->scoreFor < $this->scoreAgainst => 'loss',
            default => 'draw',
        };
    }
}
