<?php

namespace App\Repository;

use App\Entity\Plan;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plan::class);
    }

    public function findByTeam(Team $team): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.team = :team')
            ->setParameter('team', $team)
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
