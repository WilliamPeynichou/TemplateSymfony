<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\TacticalStrategyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MyStrategiesController extends AbstractController
{
    #[Route('/tactiques', name: 'app_my_strategies', methods: ['GET'])]
    public function index(TacticalStrategyRepository $strategyRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $strategies = $strategyRepo->findByCoach($user);
        $teams      = $user->getTeams()->toArray();

        return $this->render('strategy/my.html.twig', [
            'strategies' => $strategies,
            'teams'      => $teams,
        ]);
    }
}
