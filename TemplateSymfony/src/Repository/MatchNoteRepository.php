<?php

namespace App\Repository;

use App\Entity\MatchNote;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MatchNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchNote::class);
    }

    /** @return MatchNote[] */
    public function findByTeamOrderedByDate(Team $team): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.team = :team')
            ->setParameter('team', $team)
            ->orderBy('n.matchDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
