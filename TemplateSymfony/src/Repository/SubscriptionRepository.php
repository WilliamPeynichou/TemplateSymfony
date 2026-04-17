<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function findActiveForUser(User $user): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :u')
            ->andWhere('s.status IN (:active)')
            ->setParameter('u', $user)
            ->setParameter('active', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByStripeSubscriptionId(string $id): ?Subscription
    {
        return $this->findOneBy(['stripeSubscriptionId' => $id]);
    }
}
