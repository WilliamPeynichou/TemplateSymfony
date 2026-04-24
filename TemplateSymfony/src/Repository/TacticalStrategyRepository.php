<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TacticalStrategy;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TacticalStrategy>
 */
class TacticalStrategyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TacticalStrategy::class);
    }

    /** @return TacticalStrategy[] */
    public function findByTeamAndMode(Team $team, string $mode): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.team = :t')
            ->andWhere('s.mode = :mode')
            ->setParameter('t', $team)
            ->setParameter('mode', $mode)
            ->orderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return TacticalStrategy[] */
    public function findByTeam(Team $team): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.team = :t')
            ->setParameter('t', $team)
            ->orderBy('s.isDefault', 'DESC')
            ->addOrderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findDefaultForTeam(Team $team): ?TacticalStrategy
    {
        return $this->createQueryBuilder('s')
            ->where('s.team = :t')
            ->andWhere('s.isDefault = 1')
            ->setParameter('t', $team)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Pourcentage d'utilisation de chaque formation pour l'équipe. */
    public function getFormationUsageStats(Team $team): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.formation AS formation, SUM(s.usageCount) AS uses')
            ->where('s.team = :t')
            ->setParameter('t', $team)
            ->groupBy('s.formation')
            ->getQuery()
            ->getArrayResult();

        $total = array_sum(array_map(fn ($r) => (int) $r['uses'], $rows));
        if ($total === 0) return [];

        $stats = [];
        foreach ($rows as $r) {
            $uses = (int) $r['uses'];
            if ($uses <= 0) continue;
            $stats[] = [
                'formation' => $r['formation'],
                'uses'      => $uses,
                'percent'   => (int) round(($uses / $total) * 100),
            ];
        }
        usort($stats, fn ($a, $b) => $b['uses'] <=> $a['uses']);
        return $stats;
    }
}
