<?php

namespace App\Repository;

use App\Entity\AgentConversation;
use App\Entity\AgentMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AgentMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentMessage::class);
    }

    /** @return AgentMessage[] — les N derniers messages de la conversation */
    public function findLastByConversation(AgentConversation $conv, int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversation = :conv')
            ->setParameter('conv', $conv)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
