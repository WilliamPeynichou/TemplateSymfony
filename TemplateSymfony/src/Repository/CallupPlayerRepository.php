<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CallupPlayer;
use App\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CallupPlayer>
 */
class CallupPlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CallupPlayer::class);
    }

    /**
     * Historique des convocations d'un joueur avec stats agrégées.
     *
     * @return array{fixture: Fixture, role: string, reason: ?string, competition: ?string, matchDate: \DateTimeImmutable}[]
     */
    public function findHistoryForPlayer(Player $player, int $limit = 50): array
    {
        return $this->createQueryBuilder('cp')
            ->select('cp', 'c', 'f')
            ->join('cp.callup', 'c')
            ->join('c.fixture', 'f')
            ->where('cp.player = :player')
            ->setParameter('player', $player)
            ->orderBy('f.matchDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Stats de convocation sur la saison courante.
     *
     * @return array{total: int, starter: int, substitute: int, not_called: int, absent: int, byReason: array<string,int>}
     */
    public function getSeasonStats(Player $player, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('cp')
            ->select('cp.role, cp.reason, COUNT(cp.id) as cnt')
            ->join('cp.callup', 'c')
            ->join('c.fixture', 'f')
            ->where('cp.player = :player')
            ->andWhere('f.matchDate BETWEEN :from AND :to')
            ->setParameter('player', $player)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('cp.role, cp.reason')
            ->getQuery()
            ->getArrayResult();

        $stats = ['total' => 0, 'starter' => 0, 'substitute' => 0, 'not_called' => 0, 'absent' => 0, 'byReason' => []];
        foreach ($rows as $row) {
            $cnt = (int) $row['cnt'];
            $stats['total'] += $cnt;
            $stats[$row['role']] = ($stats[$row['role']] ?? 0) + $cnt;
            if ($row['reason']) {
                $stats['byReason'][$row['reason']] = ($stats['byReason'][$row['reason']] ?? 0) + $cnt;
            }
        }
        return $stats;
    }
}
