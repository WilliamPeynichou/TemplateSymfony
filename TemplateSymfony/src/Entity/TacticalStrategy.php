<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TacticalStrategyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stratégie tactique avancée (formation + instructions d'équipe).
 */
#[ORM\Entity(repositoryClass: TacticalStrategyRepository::class)]
#[ORM\Table(name: 'tactical_strategy')]
class TacticalStrategy
{
    public const MENTALITIES = [
        'Très défensive' => 'very_defensive',
        'Défensive'      => 'defensive',
        'Prudente'       => 'cautious',
        'Équilibrée'     => 'balanced',
        'Positive'       => 'positive',
        'Offensive'      => 'attacking',
        'Très offensive' => 'very_attacking',
    ];

    public const PRESSING = [
        'Basse'         => 'low',
        'Moyenne'       => 'medium',
        'Haute'         => 'high',
        'Gegenpressing' => 'gegenpress',
    ];

    public const DEFENSIVE_LINES = [
        'Basse'      => 'deep',
        'Médiane'    => 'standard',
        'Haute'      => 'high',
        'Très haute' => 'very_high',
    ];

    public const BUILD_UP = [
        'Courtes passes' => 'short',
        'Mixte'          => 'mixed',
        'Directe'        => 'direct',
        'Longs ballons'  => 'long_ball',
    ];

    public const WIDTH = [
        'Resserrée' => 'narrow',
        'Standard'  => 'standard',
        'Large'     => 'wide',
    ];

    public const TEMPO = [
        'Lent'     => 'slow',
        'Standard' => 'standard',
        'Rapide'   => 'fast',
    ];

    public const ATTACKING_FOCUS = [
        'Équilibrée'       => 'balanced',
        'Aile gauche'      => 'left_flank',
        'Dans l\'axe'      => 'through_center',
        'Aile droite'      => 'right_flank',
        'Surcharge gauche' => 'overload_left',
        'Surcharge droite' => 'overload_right',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'strategies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Team $team = null;

    #[ORM\Column(length: 150)]
    private string $name = 'Nouvelle stratégie';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $formation = '4-3-3';

    #[ORM\Column(length: 30)]
    private string $mentality = 'balanced';

    #[ORM\Column(length: 20)]
    private string $pressingIntensity = 'medium';

    #[ORM\Column(length: 20)]
    private string $defensiveLine = 'standard';

    #[ORM\Column(length: 20)]
    private string $buildUpStyle = 'mixed';

    #[ORM\Column(length: 20)]
    private string $width = 'standard';

    #[ORM\Column(length: 20)]
    private string $tempo = 'standard';

    #[ORM\Column(length: 30)]
    private string $attackingFocus = 'balanced';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $inPossessionNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $outOfPossessionNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $transitionNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $setPieceNotes = null;

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column]
    private int $usageCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: FormationSlot::class, mappedBy: 'strategy', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['slotIndex' => 'ASC'])]
    private Collection $slots;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->slots = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTeam(): ?Team { return $this->team; }
    public function setTeam(?Team $team): static { $this->team = $team; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getFormation(): string { return $this->formation; }
    public function setFormation(string $formation): static { $this->formation = $formation; return $this; }

    public function getMentality(): string { return $this->mentality; }
    public function setMentality(string $mentality): static
    {
        if (!\in_array($mentality, self::MENTALITIES, true)) {
            throw new \InvalidArgumentException('Mentalité invalide.');
        }
        $this->mentality = $mentality;
        return $this;
    }

    public function getPressingIntensity(): string { return $this->pressingIntensity; }
    public function setPressingIntensity(string $v): static
    {
        if (!\in_array($v, self::PRESSING, true)) { throw new \InvalidArgumentException('Pressing invalide.'); }
        $this->pressingIntensity = $v; return $this;
    }

    public function getDefensiveLine(): string { return $this->defensiveLine; }
    public function setDefensiveLine(string $v): static
    {
        if (!\in_array($v, self::DEFENSIVE_LINES, true)) { throw new \InvalidArgumentException('Ligne défensive invalide.'); }
        $this->defensiveLine = $v; return $this;
    }

    public function getBuildUpStyle(): string { return $this->buildUpStyle; }
    public function setBuildUpStyle(string $v): static
    {
        if (!\in_array($v, self::BUILD_UP, true)) { throw new \InvalidArgumentException('Construction invalide.'); }
        $this->buildUpStyle = $v; return $this;
    }

    public function getWidth(): string { return $this->width; }
    public function setWidth(string $v): static
    {
        if (!\in_array($v, self::WIDTH, true)) { throw new \InvalidArgumentException('Largeur invalide.'); }
        $this->width = $v; return $this;
    }

    public function getTempo(): string { return $this->tempo; }
    public function setTempo(string $v): static
    {
        if (!\in_array($v, self::TEMPO, true)) { throw new \InvalidArgumentException('Tempo invalide.'); }
        $this->tempo = $v; return $this;
    }

    public function getAttackingFocus(): string { return $this->attackingFocus; }
    public function setAttackingFocus(string $v): static
    {
        if (!\in_array($v, self::ATTACKING_FOCUS, true)) { throw new \InvalidArgumentException('Focus invalide.'); }
        $this->attackingFocus = $v; return $this;
    }

    public function getInPossessionNotes(): ?string { return $this->inPossessionNotes; }
    public function setInPossessionNotes(?string $v): static { $this->inPossessionNotes = $v; return $this; }

    public function getOutOfPossessionNotes(): ?string { return $this->outOfPossessionNotes; }
    public function setOutOfPossessionNotes(?string $v): static { $this->outOfPossessionNotes = $v; return $this; }

    public function getTransitionNotes(): ?string { return $this->transitionNotes; }
    public function setTransitionNotes(?string $v): static { $this->transitionNotes = $v; return $this; }

    public function getSetPieceNotes(): ?string { return $this->setPieceNotes; }
    public function setSetPieceNotes(?string $v): static { $this->setPieceNotes = $v; return $this; }

    public function isDefault(): bool { return $this->isDefault; }
    public function setIsDefault(bool $v): static { $this->isDefault = $v; return $this; }

    public function getUsageCount(): int { return $this->usageCount; }
    public function incrementUsage(): static { $this->usageCount++; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function getSlots(): Collection { return $this->slots; }

    public function labelFor(string $field): string
    {
        $maps = [
            'mentality'         => self::MENTALITIES,
            'pressingIntensity' => self::PRESSING,
            'defensiveLine'     => self::DEFENSIVE_LINES,
            'buildUpStyle'      => self::BUILD_UP,
            'width'             => self::WIDTH,
            'tempo'             => self::TEMPO,
            'attackingFocus'    => self::ATTACKING_FOCUS,
        ];
        $map = $maps[$field] ?? [];
        $value = $this->$field ?? null;
        return $value ? (array_search($value, $map, true) ?: (string) $value) : '—';
    }
}
