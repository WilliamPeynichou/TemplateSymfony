<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Subscription;
use App\Entity\SubscriptionPlan;
use App\Entity\User;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service d'orchestration Stripe.
 *
 * Volontairement piloté par interfaces plutôt que par le SDK Stripe pour ne
 * pas imposer la dépendance `stripe/stripe-php` tant que les clés ne sont pas
 * provisionnées. Quand on voudra activer réellement Stripe :
 *
 *   composer require stripe/stripe-php
 *
 * puis décommenter/implémenter les `createCheckoutSession` et `handleWebhook`.
 *
 * Pour la démo, createCheckoutSession retourne une URL de succès fictive.
 */
final class StripeBillingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SubscriptionRepository $subscriptionRepo,
        private readonly SubscriptionPlanRepository $planRepo,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::STRIPE_SECRET_KEY)%')]
        private readonly ?string $stripeSecretKey = null,
        #[Autowire('%env(default::STRIPE_WEBHOOK_SECRET)%')]
        private readonly ?string $stripeWebhookSecret = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return !empty($this->stripeSecretKey) && !empty($this->stripeWebhookSecret);
    }

    /**
     * Démarre une Checkout Session. Retourne l'URL à laquelle rediriger l'utilisateur.
     *
     * @return array{url: string, mode: 'stripe'|'fallback'}
     */
    public function createCheckoutSession(User $user, SubscriptionPlan $plan, string $successUrl, string $cancelUrl): array
    {
        if (!$this->isConfigured() || null === $plan->getStripePriceId()) {
            $this->logger->info('[Stripe] Non configuré, fallback mode: inscription directe au plan.', [
                'user' => $user->getId(),
                'plan' => $plan->getSlug(),
            ]);
            // Fallback : bascule immédiate côté DB sans paiement
            $this->assignPlan($user, $plan);

            return ['url' => $successUrl, 'mode' => 'fallback'];
        }

        // Dès que stripe/stripe-php sera installé, remplacer par :
        //
        //   \Stripe\Stripe::setApiKey($this->stripeSecretKey);
        //   $session = \Stripe\Checkout\Session::create([
        //       'mode' => 'subscription',
        //       'customer_email' => $user->getEmail(),
        //       'line_items' => [['price' => $plan->getStripePriceId(), 'quantity' => 1]],
        //       'success_url' => $successUrl,
        //       'cancel_url' => $cancelUrl,
        //       'metadata' => ['user_id' => (string) $user->getId(), 'plan_slug' => $plan->getSlug()],
        //   ]);
        //   return ['url' => $session->url, 'mode' => 'stripe'];

        throw new \LogicException('Stripe configuré mais SDK stripe-php absent. Lancer: composer require stripe/stripe-php');
    }

    /**
     * Gère un webhook Stripe (checkout.session.completed, invoice.paid, subscription.deleted…).
     *
     * @param array<string, mixed> $event
     */
    public function handleWebhook(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $data = $event['data']['object'] ?? [];

        switch ($type) {
            case 'checkout.session.completed':
                $userId = (int) ($data['metadata']['user_id'] ?? 0);
                $planSlug = (string) ($data['metadata']['plan_slug'] ?? '');
                $stripeSubId = (string) ($data['subscription'] ?? '');
                $stripeCustomerId = (string) ($data['customer'] ?? '');
                if ($userId <= 0 || '' === $planSlug) {
                    $this->logger->warning('[Stripe] checkout.session.completed sans metadata exploitables.');

                    return;
                }
                $user = $this->em->find(User::class, $userId);
                $plan = $this->planRepo->findOneBySlug($planSlug);
                if (!$user || !$plan) {
                    return;
                }
                $sub = $this->subscriptionRepo->findActiveForUser($user) ?? new Subscription();
                $sub->setUser($user)
                    ->setPlan($plan)
                    ->setStatus(Subscription::STATUS_ACTIVE)
                    ->setStripeCustomerId($stripeCustomerId ?: null)
                    ->setStripeSubscriptionId($stripeSubId ?: null);
                $this->em->persist($sub);
                $this->em->flush();
                break;

            case 'customer.subscription.deleted':
                $sub = $this->subscriptionRepo->findOneByStripeSubscriptionId((string) ($data['id'] ?? ''));
                if ($sub) {
                    $sub->setStatus(Subscription::STATUS_CANCELED);
                    $this->em->flush();
                }
                break;

            case 'invoice.payment_failed':
                $sub = $this->subscriptionRepo->findOneByStripeSubscriptionId((string) ($data['subscription'] ?? ''));
                if ($sub) {
                    $sub->setStatus(Subscription::STATUS_PAST_DUE);
                    $this->em->flush();
                }
                break;

            default:
                $this->logger->debug('[Stripe] Webhook ignoré', ['type' => $type]);
        }
    }

    public function assignPlan(User $user, SubscriptionPlan $plan): Subscription
    {
        $sub = $this->subscriptionRepo->findActiveForUser($user) ?? new Subscription();
        $sub->setUser($user)->setPlan($plan)->setStatus(Subscription::STATUS_ACTIVE);
        $this->em->persist($sub);
        $this->em->flush();

        return $sub;
    }

    public function ensureFreePlan(User $user): Subscription
    {
        $existing = $this->subscriptionRepo->findActiveForUser($user);
        if ($existing) {
            return $existing;
        }
        $free = $this->planRepo->findOneBySlug(SubscriptionPlan::SLUG_FREE);
        if (!$free) {
            throw new \RuntimeException('Plan "free" introuvable. Lancer les fixtures.');
        }

        return $this->assignPlan($user, $free);
    }
}
