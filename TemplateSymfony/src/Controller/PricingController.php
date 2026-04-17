<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PricingController extends AbstractController
{
    #[IsGranted('PUBLIC_ACCESS')]
    #[Route('/pricing', name: 'app_pricing', methods: ['GET'])]
    public function index(): Response
    {
        $plans = [
            [
                'id' => 'free',
                'name' => 'Free',
                'price' => '0 €',
                'period' => 'toujours',
                'description' => 'Pour tester Andfield sur une seule équipe.',
                'features' => [
                    '1 équipe',
                    'Jusqu\'à 25 joueurs',
                    'Composition tactique',
                    'Notes de match (max 10)',
                    'Agent IA limité (20 messages/mois)',
                ],
                'cta' => 'Commencer gratuitement',
                'highlighted' => false,
            ],
            [
                'id' => 'club',
                'name' => 'Club',
                'price' => '9 €',
                'period' => '/mois',
                'description' => 'Pour un coach qui gère une équipe sérieusement toute la saison.',
                'features' => [
                    'Équipes illimitées',
                    'Joueurs illimités',
                    'Notes de match illimitées',
                    'Agent IA 300 messages/mois',
                    'Export CSV',
                    'Support email',
                ],
                'cta' => 'Choisir Club',
                'highlighted' => true,
            ],
            [
                'id' => 'club_plus',
                'name' => 'Club+',
                'price' => '19 €',
                'period' => '/mois',
                'description' => 'Pour un club avec plusieurs coachs et staff technique.',
                'features' => [
                    'Tout du plan Club',
                    'Multi-coachs (jusqu\'à 5)',
                    'Agent IA illimité',
                    'Rapports PDF',
                    'Calendrier partagé + export ICS',
                    'Support prioritaire',
                ],
                'cta' => 'Choisir Club+',
                'highlighted' => false,
            ],
        ];

        return $this->render('pricing/index.html.twig', [
            'plans' => $plans,
        ]);
    }
}
