<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Player;
use App\Entity\PlayerAttributes;
use App\Entity\TacticalStrategy;
use App\Entity\Team;

/**
 * Analyse de l'effectif à la Football Manager :
 * - moyennes / profondeur par poste
 * - meilleur XI selon une formation/stratégie
 * - alertes (blessés, conditions basses, moral)
 */
final class SquadAnalyzer
{
    /**
     * @param iterable<Player> $players
     * @return array{
     *   size:int,
     *   available:int,
     *   injured:int,
     *   absent:int,
     *   avgCurrentAbility:int,
     *   byPositionGroup: array<string, array{count:int, avgRating:int, players:list<array{id:int,fullName:string,rating:int}>}>,
     *   morale: array<string,int>,
     *   lowCondition: list<array{id:int,fullName:string,condition:int}>
     * }
     */
    public function analyzeSquad(iterable $players): array
    {
        $available = 0; $injured = 0; $absent = 0;
        $ratings = [];
        $byGroup = ['GK'=>[], 'DEF'=>[], 'MID'=>[], 'ATT'=>[]];
        $morale = ['poor'=>0,'okay'=>0,'good'=>0,'excellent'=>0];
        $lowCondition = [];
        $size = 0;

        foreach ($players as $player) {
            $size++;
            match ($player->getStatus()) {
                Player::STATUS_PRESENT => $available++,
                Player::STATUS_INJURED => $injured++,
                Player::STATUS_ABSENT  => $absent++,
                default => null,
            };

            $attrs = $player->getAttributes();
            $rating = $attrs ? $attrs->getCurrentAbility($player->getPosition() ?? 'CM') : 0;
            if ($rating > 0) $ratings[] = $rating;

            $byGroup[$player->getPositionGroup()][] = [
                'id'       => $player->getId(),
                'fullName' => $player->getFullName(),
                'rating'   => $rating,
            ];

            if ($attrs) {
                $morale[$attrs->getMorale()] = ($morale[$attrs->getMorale()] ?? 0) + 1;
                if ($attrs->getCondition() < 70) {
                    $lowCondition[] = [
                        'id'        => $player->getId(),
                        'fullName'  => $player->getFullName(),
                        'condition' => $attrs->getCondition(),
                    ];
                }
            }
        }

        $result = [
            'size'              => $size,
            'available'         => $available,
            'injured'           => $injured,
            'absent'            => $absent,
            'avgCurrentAbility' => $ratings ? (int) round(array_sum($ratings) / count($ratings)) : 0,
            'byPositionGroup'   => [],
            'morale'            => $morale,
            'lowCondition'      => $lowCondition,
        ];

        foreach ($byGroup as $group => $list) {
            $groupRatings = array_filter(array_column($list, 'rating'));
            usort($list, fn ($a, $b) => $b['rating'] <=> $a['rating']);
            $result['byPositionGroup'][$group] = [
                'count'     => count($list),
                'avgRating' => $groupRatings ? (int) round(array_sum($groupRatings) / count($groupRatings)) : 0,
                'players'   => $list,
            ];
        }

        return $result;
    }

    /**
     * Suggère le meilleur XI selon les rôles de la stratégie.
     * @return array<int,array{slot:int,label:string,positionCode:string,role:string,suggestedPlayer:?array{id:int,fullName:string,suitability:int}}>
     */
    public function suggestBestEleven(Team $team, TacticalStrategy $strategy): array
    {
        /** @var Player[] $eligible */
        $eligible = [];
        foreach ($team->getPlayers() as $p) {
            if ($p->getStatus() === Player::STATUS_PRESENT && $p->getAttributes() !== null) {
                $eligible[] = $p;
            }
        }

        $suggestions = [];
        $used = [];
        foreach ($strategy->getSlots() as $slot) {
            $best = null;
            $bestScore = -1;
            foreach ($eligible as $p) {
                if (isset($used[$p->getId()])) continue;
                if ($p->getPositionGroup() !== $slot->getPositionGroup()
                    && !($slot->getPositionGroup() === 'GK' && $p->getPosition() === 'GK')) {
                    continue;
                }
                /** @var PlayerAttributes $attrs */
                $attrs = $p->getAttributes();
                $score = RoleLibrary::suitability($attrs, $slot->getRole());
                // léger bonus si poste principal strictement identique
                if ($p->getPosition() === $slot->getPositionCode()) $score += 5;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $p;
                }
            }
            if ($best) {
                $used[$best->getId()] = true;
                $suggestions[] = [
                    'slot'         => $slot->getSlotIndex(),
                    'label'        => $slot->getLabel(),
                    'positionCode' => $slot->getPositionCode(),
                    'role'         => $slot->getRole(),
                    'suggestedPlayer' => [
                        'id'          => $best->getId(),
                        'fullName'    => $best->getFullName(),
                        'suitability' => min(100, $bestScore),
                    ],
                ];
            } else {
                $suggestions[] = [
                    'slot'         => $slot->getSlotIndex(),
                    'label'        => $slot->getLabel(),
                    'positionCode' => $slot->getPositionCode(),
                    'role'         => $slot->getRole(),
                    'suggestedPlayer' => null,
                ];
            }
        }
        return $suggestions;
    }
}
