<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Player;
use App\Entity\PlayerStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlayerStatusHistory>
 */
class PlayerStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlayerStatusHistory::class);
    }

    /** @return PlayerStatusHistory[] */
    public function findLatestForPlayer(Player $player): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.player = :player')
            ->setParameter('player', $player)
            ->orderBy('h.changedAt', 'DESC')
            ->addOrderBy('h.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
