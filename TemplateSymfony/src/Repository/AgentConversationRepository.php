<?php

namespace App\Repository;

use App\Entity\AgentConversation;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AgentConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentConversation::class);
    }

    /** @return AgentConversation[] */
    public function findByCoach(User $coach, ?Team $team = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.coach = :coach')
            ->setParameter('coach', $coach)
            ->orderBy('c.updatedAt', 'DESC');

        if ($team) {
            $qb->andWhere('c.team = :team')->setParameter('team', $team);
        }

        return $qb->getQuery()->getResult();
    }
}
