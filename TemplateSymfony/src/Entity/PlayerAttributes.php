<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlayerAttributesRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Attributs Football Manager-like d'un joueur (échelle 1-20).
 */
#[ORM\Entity(repositoryClass: PlayerAttributesRepository::class)]
#[ORM\Table(name: 'player_attributes')]
class PlayerAttributes
{
    public const MORALE_POOR      = 'poor';
    public const MORALE_OKAY      = 'okay';
    public const MORALE_GOOD      = 'good';
    public const MORALE_EXCELLENT = 'excellent';

    public const MORALES = [
        'Faible'    => self::MORALE_POOR,
        'Correct'   => self::MORALE_OKAY,
        'Bon'       => self::MORALE_GOOD,
        'Excellent' => self::MORALE_EXCELLENT,
    ];

    public const TECHNICAL_ATTRIBUTES = [
        'pace'       => 'Vitesse',
        'shooting'   => 'Tir',
        'passing'    => 'Passe',
        'dribbling'  => 'Dribble',
        'crossing'   => 'Centre',
        'finishing'  => 'Finition',
        'firstTouch' => 'Contrôle',
        'heading'    => 'Jeu de tête',
        'tackling'   => 'Tacle',
        'marking'    => 'Marquage',
    ];

    public const MENTAL_ATTRIBUTES = [
        'vision'        => 'Vision',
        'composure'     => 'Sang-froid',
        'decisions'     => 'Décisions',
        'workRate'      => 'Volume de jeu',
        'leadership'    => 'Leadership',
        'aggression'    => 'Agressivité',
        'positioning'   => 'Placement',
        'concentration' => 'Concentration',
    ];

    public const PHYSICAL_ATTRIBUTES = [
        'stamina'      => 'Endurance',
        'strength'     => 'Puissance',
        'agility'      => 'Agilité',
        'balance'      => 'Équilibre',
        'jumping'      => 'Détente',
        'acceleration' => 'Accélération',
    ];

    public const GOALKEEPING_ATTRIBUTES = [
        'reflexes'       => 'Réflexes',
        'handling'       => 'Prise de balle',
        'kicking'        => 'Relance au pied',
        'oneOnOnes'      => 'Face-à-face',
        'commandOfArea'  => 'Sortie aérienne',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'attributes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Player $player = null;

    // Technical
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $pace = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $shooting = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $passing = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $dribbling = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $crossing = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $finishing = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $firstTouch = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $heading = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $tackling = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $marking = 10;

    // Mental
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $vision = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $composure = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $decisions = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $workRate = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $leadership = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $aggression = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $positioning = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $concentration = 10;

    // Physical
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $stamina = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $strength = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $agility = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $balance = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $jumping = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $acceleration = 10;

    // Goalkeeping
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $reflexes = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $handling = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $kicking = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $oneOnOnes = 10;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 20)] private int $commandOfArea = 10;

    // State
    #[ORM\Column(name: '`condition`', type: 'smallint')] #[Assert\Range(min: 0, max: 100)] private int $condition = 100;
    #[ORM\Column(length: 20)] private string $morale = self::MORALE_OKAY;
    #[ORM\Column(type: 'smallint')] #[Assert\Range(min: 1, max: 100)] private int $potentialAbility = 50;
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getPlayer(): ?Player { return $this->player; }
    public function setPlayer(?Player $player): static { $this->player = $player; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    // Generic accessor used by services/templates for dynamic attribute lookups.
    public function get(string $name): int
    {
        if (!property_exists($this, $name)) {
            throw new \InvalidArgumentException(sprintf('Attribut inconnu: %s', $name));
        }
        return (int) $this->$name;
    }

    public function set(string $name, int $value): void
    {
        if (!property_exists($this, $name)) {
            throw new \InvalidArgumentException(sprintf('Attribut inconnu: %s', $name));
        }
        $this->$name = max(1, min(20, $value));
    }

    public function getCondition(): int { return $this->condition; }
    public function setCondition(int $v): static { $this->condition = max(0, min(100, $v)); return $this; }

    public function getMorale(): string { return $this->morale; }
    public function setMorale(string $m): static
    {
        if (!\in_array($m, self::MORALES, true)) {
            throw new \InvalidArgumentException('Morale invalide: '.$m);
        }
        $this->morale = $m;
        return $this;
    }

    public function getMoraleLabel(): string
    {
        return array_search($this->morale, self::MORALES, true) ?: $this->morale;
    }

    public function getPotentialAbility(): int { return $this->potentialAbility; }
    public function setPotentialAbility(int $v): static { $this->potentialAbility = max(1, min(100, $v)); return $this; }

    /** Current Ability — on 100, agrégat pondéré des attributs pertinents au poste principal. */
    public function getCurrentAbility(string $position = 'CM'): int
    {
        $weights = self::positionWeights($position);
        $sum = 0;
        $total = 0;
        foreach ($weights as $attr => $w) {
            $sum   += $this->get($attr) * $w;
            $total += $w;
        }
        if ($total === 0) return 0;
        // 1-20 scale → 1-100
        return (int) round(($sum / $total) * 5);
    }

    /** Pondération des attributs pour une note par poste. */
    public static function positionWeights(string $position): array
    {
        return match ($position) {
            'GK' => [
                'reflexes'=>5,'handling'=>4,'oneOnOnes'=>4,'commandOfArea'=>3,'kicking'=>3,
                'positioning'=>4,'concentration'=>4,'decisions'=>3,
                'agility'=>4,'jumping'=>3,
            ],
            'CB' => [
                'tackling'=>5,'marking'=>5,'heading'=>5,'strength'=>4,'positioning'=>5,
                'composure'=>3,'concentration'=>4,'passing'=>3,'jumping'=>3,'aggression'=>3,
            ],
            'LB', 'RB' => [
                'tackling'=>4,'marking'=>4,'crossing'=>4,'pace'=>5,'stamina'=>5,
                'positioning'=>4,'workRate'=>4,'passing'=>3,'acceleration'=>4,
            ],
            'CDM' => [
                'tackling'=>5,'marking'=>4,'positioning'=>5,'passing'=>4,
                'workRate'=>5,'decisions'=>4,'stamina'=>4,'composure'=>3,'aggression'=>3,
            ],
            'CM' => [
                'passing'=>5,'vision'=>4,'decisions'=>4,'stamina'=>5,'workRate'=>5,
                'firstTouch'=>4,'composure'=>3,'tackling'=>3,
            ],
            'CAM' => [
                'passing'=>5,'vision'=>5,'dribbling'=>4,'firstTouch'=>5,'shooting'=>4,
                'composure'=>4,'decisions'=>4,'finishing'=>3,
            ],
            'LW', 'RW' => [
                'pace'=>5,'acceleration'=>5,'dribbling'=>5,'crossing'=>4,'finishing'=>4,
                'firstTouch'=>4,'agility'=>4,'balance'=>3,
            ],
            'ST' => [
                'finishing'=>5,'shooting'=>5,'composure'=>5,'positioning'=>5,
                'heading'=>4,'firstTouch'=>4,'pace'=>4,'strength'=>3,
            ],
            default => [
                'passing'=>3,'stamina'=>3,'decisions'=>3,'workRate'=>3,'firstTouch'=>3,
            ],
        };
    }
}
