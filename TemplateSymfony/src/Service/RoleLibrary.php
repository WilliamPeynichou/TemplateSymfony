<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PlayerAttributes;

/**
 * Catalogue des rôles FM-like + calcul de suitability joueur→rôle.
 */
final class RoleLibrary
{
    /**
     * Chaque rôle liste ses attributs clés (pondérés 1-5).
     * @return array<string, array{label:string, group:string, key:array<string,int>}>
     */
    public static function all(): array
    {
        return [
            // Gardien
            'shot_stopper' => [
                'label' => 'Gardien de but',
                'group' => 'GK',
                'key' => ['reflexes'=>5,'handling'=>5,'positioning'=>4,'concentration'=>4,'oneOnOnes'=>4,'agility'=>4],
            ],
            'sweeper_keeper' => [
                'label' => 'Gardien libéro',
                'group' => 'GK',
                'key' => ['reflexes'=>4,'kicking'=>5,'oneOnOnes'=>4,'commandOfArea'=>4,'decisions'=>5,'passing'=>4,'pace'=>3],
            ],
            // Défense
            'no_nonsense_cb' => [
                'label' => 'Défenseur central strict',
                'group' => 'DEF',
                'key' => ['tackling'=>5,'marking'=>5,'heading'=>5,'strength'=>5,'jumping'=>4,'positioning'=>4,'aggression'=>4,'concentration'=>4],
            ],
            'ball_playing_defender' => [
                'label' => 'Défenseur relanceur',
                'group' => 'DEF',
                'key' => ['tackling'=>4,'marking'=>4,'passing'=>5,'vision'=>4,'composure'=>4,'positioning'=>5,'firstTouch'=>4,'decisions'=>4],
            ],
            'full_back' => [
                'label' => 'Latéral classique',
                'group' => 'DEF',
                'key' => ['tackling'=>4,'marking'=>4,'crossing'=>4,'stamina'=>5,'pace'=>4,'positioning'=>4,'workRate'=>4],
            ],
            'wing_back' => [
                'label' => 'Piston',
                'group' => 'DEF',
                'key' => ['crossing'=>5,'dribbling'=>4,'stamina'=>5,'pace'=>5,'acceleration'=>4,'workRate'=>5,'tackling'=>3],
            ],
            'inverted_wing_back' => [
                'label' => 'Latéral rentrant',
                'group' => 'DEF',
                'key' => ['passing'=>5,'vision'=>4,'firstTouch'=>4,'dribbling'=>4,'stamina'=>4,'decisions'=>4,'positioning'=>4],
            ],
            // Milieu
            'ball_winning_midfielder' => [
                'label' => 'Récupérateur',
                'group' => 'MID',
                'key' => ['tackling'=>5,'marking'=>4,'aggression'=>5,'workRate'=>5,'stamina'=>5,'strength'=>4,'positioning'=>4],
            ],
            'deep_lying_playmaker' => [
                'label' => 'Meneur reculé',
                'group' => 'MID',
                'key' => ['passing'=>5,'vision'=>5,'decisions'=>5,'composure'=>4,'firstTouch'=>5,'positioning'=>4],
            ],
            'regista' => [
                'label' => 'Régista',
                'group' => 'MID',
                'key' => ['passing'=>5,'vision'=>5,'firstTouch'=>5,'composure'=>5,'decisions'=>5,'dribbling'=>4,'leadership'=>4],
            ],
            'box_to_box' => [
                'label' => 'Box-to-box',
                'group' => 'MID',
                'key' => ['stamina'=>5,'workRate'=>5,'passing'=>4,'shooting'=>4,'tackling'=>4,'decisions'=>4,'pace'=>4],
            ],
            'mezzala' => [
                'label' => 'Mezzala',
                'group' => 'MID',
                'key' => ['passing'=>5,'dribbling'=>4,'vision'=>4,'firstTouch'=>5,'shooting'=>4,'workRate'=>4,'acceleration'=>4],
            ],
            'advanced_playmaker' => [
                'label' => 'Meneur avancé',
                'group' => 'MID',
                'key' => ['passing'=>5,'vision'=>5,'firstTouch'=>5,'dribbling'=>4,'decisions'=>5,'composure'=>4],
            ],
            'roaming_playmaker' => [
                'label' => 'Meneur en liberté',
                'group' => 'MID',
                'key' => ['passing'=>5,'vision'=>5,'firstTouch'=>5,'dribbling'=>4,'decisions'=>5,'stamina'=>5,'workRate'=>5],
            ],
            'trequartista' => [
                'label' => 'Trequartista',
                'group' => 'MID',
                'key' => ['passing'=>5,'vision'=>5,'dribbling'=>5,'firstTouch'=>5,'decisions'=>4,'composure'=>4,'finishing'=>4],
            ],
            // Attaque
            'winger' => [
                'label' => 'Ailier',
                'group' => 'ATT',
                'key' => ['pace'=>5,'acceleration'=>5,'dribbling'=>5,'crossing'=>5,'agility'=>4,'balance'=>4,'workRate'=>4],
            ],
            'inside_forward' => [
                'label' => 'Ailier rentrant',
                'group' => 'ATT',
                'key' => ['pace'=>5,'dribbling'=>5,'finishing'=>4,'firstTouch'=>4,'composure'=>4,'acceleration'=>5,'shooting'=>4],
            ],
            'poacher' => [
                'label' => 'Renard des surfaces',
                'group' => 'ATT',
                'key' => ['finishing'=>5,'positioning'=>5,'composure'=>5,'pace'=>4,'acceleration'=>4,'firstTouch'=>4],
            ],
            'target_man' => [
                'label' => 'Pivot',
                'group' => 'ATT',
                'key' => ['heading'=>5,'strength'=>5,'jumping'=>5,'finishing'=>4,'firstTouch'=>4,'balance'=>4,'composure'=>3],
            ],
            'complete_forward' => [
                'label' => 'Avant-centre complet',
                'group' => 'ATT',
                'key' => ['finishing'=>5,'firstTouch'=>5,'composure'=>4,'passing'=>4,'dribbling'=>4,'heading'=>4,'pace'=>4,'strength'=>4],
            ],
            'pressing_forward' => [
                'label' => 'Attaquant presseur',
                'group' => 'ATT',
                'key' => ['workRate'=>5,'stamina'=>5,'aggression'=>4,'finishing'=>4,'pace'=>4,'acceleration'=>4,'positioning'=>4],
            ],
            'false_9' => [
                'label' => 'Faux 9',
                'group' => 'ATT',
                'key' => ['passing'=>5,'vision'=>5,'dribbling'=>5,'firstTouch'=>5,'composure'=>5,'decisions'=>5,'finishing'=>3],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return array<string, array{label:string, key:array<string,int>}> rôles filtrés par groupe (GK/DEF/MID/ATT)
     */
    public static function byGroup(string $group): array
    {
        return array_filter(self::all(), fn ($r) => $r['group'] === $group);
    }

    /**
     * Calcule la suitability (0-100%) d'un joueur pour un rôle donné.
     */
    public static function suitability(PlayerAttributes $attrs, string $roleKey): int
    {
        $role = self::get($roleKey);
        if (!$role) return 0;

        $sum = 0;
        $weightSum = 0;
        foreach ($role['key'] as $attrName => $weight) {
            $sum       += $attrs->get($attrName) * $weight;
            $weightSum += $weight;
        }

        if ($weightSum === 0) return 0;

        // 1-20 → 0-100
        return (int) round(($sum / ($weightSum * 20)) * 100);
    }

    public static function roleLabel(string $roleKey): string
    {
        return self::get($roleKey)['label'] ?? $roleKey;
    }
}
