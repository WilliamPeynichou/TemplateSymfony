<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('PUBLIC_ACCESS')]
class PwaController extends AbstractController
{
    #[Route('/offline', name: 'app_offline', methods: ['GET'])]
    public function offline(): Response
    {
        return $this->render('pages/offline.html.twig');
    }
}
