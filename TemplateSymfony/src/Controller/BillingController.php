<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\SubscriptionRepository;
use App\Service\StripeBillingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BillingController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/billing', name: 'app_billing', methods: ['GET'])]
    public function index(SubscriptionRepository $subs, SubscriptionPlanRepository $plans): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $current = $subs->findActiveForUser($user);

        return $this->render('billing/index.html.twig', [
            'currentSubscription' => $current,
            'availablePlans' => $plans->findActive(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/billing/checkout/{slug}', name: 'app_billing_checkout', methods: ['POST'])]
    public function checkout(
        string $slug,
        Request $request,
        SubscriptionPlanRepository $plans,
        StripeBillingService $billing,
    ): Response {
        if (!$this->isCsrfTokenValid('billing_checkout_'.$slug, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        $plan = $plans->findOneBySlug($slug);
        if (!$plan || !$plan->isActive()) {
            throw $this->createNotFoundException('Plan inconnu.');
        }

        /** @var User $user */
        $user = $this->getUser();

        $result = $billing->createCheckoutSession(
            $user,
            $plan,
            successUrl: $this->generateUrl('app_billing', [], 0),
            cancelUrl: $this->generateUrl('app_pricing', [], 0),
        );

        if ('fallback' === $result['mode']) {
            $this->addFlash('success', sprintf('Plan %s activé (mode dev).', $plan->getName()));
        }

        return $this->redirect($result['url']);
    }

    #[IsGranted('PUBLIC_ACCESS')]
    #[Route('/billing/webhook', name: 'app_billing_webhook', methods: ['POST'])]
    public function webhook(Request $request, StripeBillingService $billing): JsonResponse
    {
        $payload = $request->getContent();
        $event = json_decode($payload, true);
        if (!\is_array($event)) {
            return new JsonResponse(['error' => 'invalid_payload'], 400);
        }

        // TODO: vérifier la signature Stripe via \Stripe\Webhook::constructEvent
        // dès que stripe/stripe-php sera installé.
        $billing->handleWebhook($event);

        return new JsonResponse(['received' => true]);
    }
}
