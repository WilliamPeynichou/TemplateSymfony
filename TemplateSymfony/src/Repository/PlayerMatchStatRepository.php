<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Fixture;
use App\Entity\Player;
use App\Entity\PlayerMatchStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlayerMatchStat>
 */
class PlayerMatchStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlayerMatchStat::class);
    }

    public function findOneByPlayerAndFixture(Player $player, Fixture $fixture): ?PlayerMatchStat
    {
        return $this->findOneBy(['player' => $player, 'fixture' => $fixture]);
    }

    /**
     * Agrège les stats d'un joueur sur une saison (optionnellement filtrée par status).
     *
     * @return array{matches: int, minutes: int, goals: int, assists: int, yellow: int, red: int, avgRating: ?float}
     */
    public function aggregateForPlayer(Player $player): array
    {
        $stats = $this->createQueryBuilder('s')
            ->where('s.player = :p')
            ->setParameter('p', $player)
            ->getQuery()
            ->getResult();

        $matches = 0;
        $minutes = 0;
        $goals = 0;
        $assists = 0;
        $yellow = 0;
        $red = 0;
        $ratingsSum = 0.0;
        $ratingsCount = 0;

        foreach ($stats as $s) {
            /** @var PlayerMatchStat $s */
            ++$matches;
            $minutes += $s->getMinutesPlayed();
            $goals += $s->getGoals();
            $assists += $s->getAssists();
            $yellow += $s->getYellowCards();
            $red += $s->getRedCards();
            if (null !== $s->getRating()) {
                $ratingsSum += (float) $s->getRating();
                ++$ratingsCount;
            }
        }

        return [
            'matches' => $matches,
            'minutes' => $minutes,
            'goals' => $goals,
            'assists' => $assists,
            'yellow' => $yellow,
            'red' => $red,
            'avgRating' => $ratingsCount > 0 ? round($ratingsSum / $ratingsCount, 2) : null,
        ];
    }
}
