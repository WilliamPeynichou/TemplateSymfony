<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Catalogue de formations avec positions de base (coordonnées terrain 0-100).
 * Convention repère : (0,0) = corner haut-gauche (attaque adverse), (100,100) = bas-droite (but équipe).
 * Les gardiens sont toujours placés en Y ≈ 93, les attaquants vers Y ≈ 10-20.
 */
final class FormationLibrary
{
    /**
     * @return array<string,array{label:string, slots:list<array{index:int,code:string,label:string,role:string,duty:string,x:float,y:float}>}>
     */
    public static function all(): array
    {
        return [
            '4-3-3' => [
                'label' => '4-3-3',
                'slots' => [
                    ['index'=>0,  'code'=>'GK',  'label'=>'GK',  'role'=>'sweeper_keeper',     'duty'=>'support', 'x'=>50, 'y'=>93],
                    ['index'=>1,  'code'=>'RB',  'label'=>'DD',  'role'=>'full_back',          'duty'=>'support', 'x'=>82, 'y'=>75],
                    ['index'=>2,  'code'=>'CB',  'label'=>'DC',  'role'=>'ball_playing_defender','duty'=>'defend', 'x'=>60, 'y'=>80],
                    ['index'=>3,  'code'=>'CB',  'label'=>'DC',  'role'=>'no_nonsense_cb',     'duty'=>'defend',  'x'=>40, 'y'=>80],
                    ['index'=>4,  'code'=>'LB',  'label'=>'DG',  'role'=>'full_back',          'duty'=>'support', 'x'=>18, 'y'=>75],
                    ['index'=>5,  'code'=>'CDM', 'label'=>'MDC', 'role'=>'deep_lying_playmaker','duty'=>'defend', 'x'=>50, 'y'=>60],
                    ['index'=>6,  'code'=>'CM',  'label'=>'MC',  'role'=>'box_to_box',         'duty'=>'support', 'x'=>65, 'y'=>48],
                    ['index'=>7,  'code'=>'CM',  'label'=>'MC',  'role'=>'mezzala',            'duty'=>'support', 'x'=>35, 'y'=>48],
                    ['index'=>8,  'code'=>'RW',  'label'=>'AD',  'role'=>'winger',             'duty'=>'attack',  'x'=>82, 'y'=>22],
                    ['index'=>9,  'code'=>'ST',  'label'=>'BU',  'role'=>'complete_forward',   'duty'=>'attack',  'x'=>50, 'y'=>12],
                    ['index'=>10, 'code'=>'LW',  'label'=>'AG',  'role'=>'inside_forward',     'duty'=>'attack',  'x'=>18, 'y'=>22],
                ],
            ],
            '4-4-2' => [
                'label' => '4-4-2',
                'slots' => [
                    ['index'=>0,  'code'=>'GK',  'label'=>'GK',  'role'=>'shot_stopper',       'duty'=>'defend',  'x'=>50, 'y'=>93],
                    ['index'=>1,  'code'=>'RB',  'label'=>'DD',  'role'=>'full_back',          'duty'=>'support', 'x'=>82, 'y'=>75],
                    ['index'=>2,  'code'=>'CB',  'label'=>'DC',  'role'=>'no_nonsense_cb',     'duty'=>'defend',  'x'=>60, 'y'=>80],
                    ['index'=>3,  'code'=>'CB',  'label'=>'DC',  'role'=>'ball_playing_defender','duty'=>'defend','x'=>40, 'y'=>80],
                    ['index'=>4,  'code'=>'LB',  'label'=>'DG',  'role'=>'full_back',          'duty'=>'support', 'x'=>18, 'y'=>75],
                    ['index'=>5,  'code'=>'RW',  'label'=>'MD',  'role'=>'winger',             'duty'=>'support', 'x'=>82, 'y'=>48],
                    ['index'=>6,  'code'=>'CM',  'label'=>'MC',  'role'=>'ball_winning_midfielder','duty'=>'defend','x'=>58, 'y'=>52],
                    ['index'=>7,  'code'=>'CM',  'label'=>'MC',  'role'=>'box_to_box',         'duty'=>'support', 'x'=>42, 'y'=>52],
                    ['index'=>8,  'code'=>'LW',  'label'=>'MG',  'role'=>'winger',             'duty'=>'support', 'x'=>18, 'y'=>48],
                    ['index'=>9,  'code'=>'ST',  'label'=>'BU',  'role'=>'target_man',         'duty'=>'attack',  'x'=>62, 'y'=>15],
                    ['index'=>10, 'code'=>'ST',  'label'=>'BU',  'role'=>'poacher',            'duty'=>'attack',  'x'=>38, 'y'=>15],
                ],
            ],
            '4-2-3-1' => [
                'label' => '4-2-3-1',
                'slots' => [
                    ['index'=>0,  'code'=>'GK',  'label'=>'GK',  'role'=>'sweeper_keeper',     'duty'=>'support', 'x'=>50, 'y'=>93],
                    ['index'=>1,  'code'=>'RB',  'label'=>'DD',  'role'=>'full_back',          'duty'=>'support', 'x'=>82, 'y'=>75],
                    ['index'=>2,  'code'=>'CB',  'label'=>'DC',  'role'=>'ball_playing_defender','duty'=>'defend','x'=>60, 'y'=>80],
                    ['index'=>3,  'code'=>'CB',  'label'=>'DC',  'role'=>'no_nonsense_cb',     'duty'=>'defend',  'x'=>40, 'y'=>80],
                    ['index'=>4,  'code'=>'LB',  'label'=>'DG',  'role'=>'full_back',          'duty'=>'support', 'x'=>18, 'y'=>75],
                    ['index'=>5,  'code'=>'CDM', 'label'=>'MDC', 'role'=>'ball_winning_midfielder','duty'=>'defend','x'=>60,'y'=>60],
                    ['index'=>6,  'code'=>'CDM', 'label'=>'MDC', 'role'=>'deep_lying_playmaker','duty'=>'support','x'=>40, 'y'=>60],
                    ['index'=>7,  'code'=>'RW',  'label'=>'AD',  'role'=>'winger',             'duty'=>'attack',  'x'=>82, 'y'=>32],
                    ['index'=>8,  'code'=>'CAM', 'label'=>'MOC', 'role'=>'trequartista',       'duty'=>'attack',  'x'=>50, 'y'=>32],
                    ['index'=>9,  'code'=>'LW',  'label'=>'AG',  'role'=>'inside_forward',     'duty'=>'attack',  'x'=>18, 'y'=>32],
                    ['index'=>10, 'code'=>'ST',  'label'=>'BU',  'role'=>'complete_forward',   'duty'=>'attack',  'x'=>50, 'y'=>12],
                ],
            ],
            '3-5-2' => [
                'label' => '3-5-2',
                'slots' => [
                    ['index'=>0,  'code'=>'GK',  'label'=>'GK',  'role'=>'sweeper_keeper',     'duty'=>'support', 'x'=>50, 'y'=>93],
                    ['index'=>1,  'code'=>'CB',  'label'=>'DCD', 'role'=>'no_nonsense_cb',     'duty'=>'defend',  'x'=>70, 'y'=>80],
                    ['index'=>2,  'code'=>'CB',  'label'=>'DC',  'role'=>'ball_playing_defender','duty'=>'defend','x'=>50,'y'=>82],
                    ['index'=>3,  'code'=>'CB',  'label'=>'DCG', 'role'=>'no_nonsense_cb',     'duty'=>'defend',  'x'=>30, 'y'=>80],
                    ['index'=>4,  'code'=>'RB',  'label'=>'PD',  'role'=>'wing_back',          'duty'=>'attack',  'x'=>88, 'y'=>55],
                    ['index'=>5,  'code'=>'LB',  'label'=>'PG',  'role'=>'wing_back',          'duty'=>'attack',  'x'=>12, 'y'=>55],
                    ['index'=>6,  'code'=>'CDM', 'label'=>'MDC', 'role'=>'regista',            'duty'=>'support', 'x'=>50, 'y'=>58],
                    ['index'=>7,  'code'=>'CM',  'label'=>'MC',  'role'=>'mezzala',            'duty'=>'support', 'x'=>65, 'y'=>42],
                    ['index'=>8,  'code'=>'CM',  'label'=>'MC',  'role'=>'box_to_box',         'duty'=>'support', 'x'=>35, 'y'=>42],
                    ['index'=>9,  'code'=>'ST',  'label'=>'BU',  'role'=>'target_man',         'duty'=>'attack',  'x'=>60, 'y'=>15],
                    ['index'=>10, 'code'=>'ST',  'label'=>'BU',  'role'=>'poacher',            'duty'=>'attack',  'x'=>40, 'y'=>15],
                ],
            ],
            '4-1-4-1' => [
                'label' => '4-1-4-1',
                'slots' => [
                    ['index'=>0,  'code'=>'GK',  'label'=>'GK',  'role'=>'shot_stopper',       'duty'=>'defend',  'x'=>50, 'y'=>93],
                    ['index'=>1,  'code'=>'RB',  'label'=>'DD',  'role'=>'full_back',          'duty'=>'support', 'x'=>82, 'y'=>75],
                    ['index'=>2,  'code'=>'CB',  'label'=>'DC',  'role'=>'no_nonsense_cb',     'duty'=>'defend',  'x'=>60, 'y'=>80],
                    ['index'=>3,  'code'=>'CB',  'label'=>'DC',  'role'=>'ball_playing_defender','duty'=>'defend','x'=>40, 'y'=>80],
                    ['index'=>4,  'code'=>'LB',  'label'=>'DG',  'role'=>'full_back',          'duty'=>'support', 'x'=>18, 'y'=>75],
                    ['index'=>5,  'code'=>'CDM', 'label'=>'MDC', 'role'=>'ball_winning_midfielder','duty'=>'defend','x'=>50,'y'=>60],
                    ['index'=>6,  'code'=>'RW',  'label'=>'MD',  'role'=>'winger',             'duty'=>'support', 'x'=>82, 'y'=>40],
                    ['index'=>7,  'code'=>'CM',  'label'=>'MC',  'role'=>'advanced_playmaker', 'duty'=>'support', 'x'=>60, 'y'=>42],
                    ['index'=>8,  'code'=>'CM',  'label'=>'MC',  'role'=>'box_to_box',         'duty'=>'support', 'x'=>40, 'y'=>42],
                    ['index'=>9,  'code'=>'LW',  'label'=>'MG',  'role'=>'winger',             'duty'=>'support', 'x'=>18, 'y'=>40],
                    ['index'=>10, 'code'=>'ST',  'label'=>'BU',  'role'=>'pressing_forward',   'duty'=>'attack',  'x'=>50, 'y'=>15],
                ],
            ],
            '5-3-2' => [
                'label' => '5-3-2',
                'slots' => [
                    ['index'=>0,  'code'=>'GK',  'label'=>'GK',  'role'=>'shot_stopper',       'duty'=>'defend',  'x'=>50, 'y'=>93],
                    ['index'=>1,  'code'=>'RB',  'label'=>'PD',  'role'=>'wing_back',          'duty'=>'support', 'x'=>85, 'y'=>70],
                    ['index'=>2,  'code'=>'CB',  'label'=>'DCD', 'role'=>'no_nonsense_cb',     'duty'=>'defend',  'x'=>66, 'y'=>80],
                    ['index'=>3,  'code'=>'CB',  'label'=>'DC',  'role'=>'ball_playing_defender','duty'=>'defend','x'=>50,'y'=>82],
                    ['index'=>4,  'code'=>'CB',  'label'=>'DCG', 'role'=>'no_nonsense_cb',     'duty'=>'defend',  'x'=>34, 'y'=>80],
                    ['index'=>5,  'code'=>'LB',  'label'=>'PG',  'role'=>'wing_back',          'duty'=>'support', 'x'=>15, 'y'=>70],
                    ['index'=>6,  'code'=>'CDM', 'label'=>'MDC', 'role'=>'deep_lying_playmaker','duty'=>'defend', 'x'=>50, 'y'=>55],
                    ['index'=>7,  'code'=>'CM',  'label'=>'MC',  'role'=>'box_to_box',         'duty'=>'support', 'x'=>65, 'y'=>42],
                    ['index'=>8,  'code'=>'CM',  'label'=>'MC',  'role'=>'mezzala',            'duty'=>'support', 'x'=>35, 'y'=>42],
                    ['index'=>9,  'code'=>'ST',  'label'=>'BU',  'role'=>'target_man',         'duty'=>'attack',  'x'=>60, 'y'=>15],
                    ['index'=>10, 'code'=>'ST',  'label'=>'BU',  'role'=>'pressing_forward',   'duty'=>'attack',  'x'=>40, 'y'=>15],
                ],
            ],
            '3-4-3' => [
                'label' => '3-4-3',
                'slots' => [
                    ['index'=>0,  'code'=>'GK',  'label'=>'GK',  'role'=>'sweeper_keeper',     'duty'=>'support', 'x'=>50, 'y'=>93],
                    ['index'=>1,  'code'=>'CB',  'label'=>'DCD', 'role'=>'ball_playing_defender','duty'=>'defend','x'=>68,'y'=>80],
                    ['index'=>2,  'code'=>'CB',  'label'=>'DC',  'role'=>'no_nonsense_cb',     'duty'=>'defend',  'x'=>50, 'y'=>82],
                    ['index'=>3,  'code'=>'CB',  'label'=>'DCG', 'role'=>'ball_playing_defender','duty'=>'defend','x'=>32,'y'=>80],
                    ['index'=>4,  'code'=>'RB',  'label'=>'PD',  'role'=>'wing_back',          'duty'=>'attack',  'x'=>88, 'y'=>55],
                    ['index'=>5,  'code'=>'CM',  'label'=>'MC',  'role'=>'box_to_box',         'duty'=>'support', 'x'=>62, 'y'=>50],
                    ['index'=>6,  'code'=>'CM',  'label'=>'MC',  'role'=>'deep_lying_playmaker','duty'=>'support','x'=>38, 'y'=>50],
                    ['index'=>7,  'code'=>'LB',  'label'=>'PG',  'role'=>'wing_back',          'duty'=>'attack',  'x'=>12, 'y'=>55],
                    ['index'=>8,  'code'=>'RW',  'label'=>'AD',  'role'=>'winger',             'duty'=>'attack',  'x'=>78, 'y'=>18],
                    ['index'=>9,  'code'=>'ST',  'label'=>'BU',  'role'=>'complete_forward',   'duty'=>'attack',  'x'=>50, 'y'=>12],
                    ['index'=>10, 'code'=>'LW',  'label'=>'AG',  'role'=>'inside_forward',     'duty'=>'attack',  'x'=>22, 'y'=>18],
                ],
            ],
        ];
    }

    public static function get(string $key): array
    {
        $all = self::all();
        return $all[$key] ?? $all['4-3-3'];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
