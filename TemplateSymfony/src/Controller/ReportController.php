<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Team;
use App\Repository\FixtureRepository;
use App\Repository\PlayerMatchStatRepository;
use App\Repository\PlayerRepository;
use App\Service\PdfReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ReportController extends AbstractController
{
    #[Route('/teams/{id}/reports/season.pdf', name: 'app_report_season', methods: ['GET'])]
    public function season(
        Team $team,
        PlayerRepository $players,
        FixtureRepository $fixtures,
        PlayerMatchStatRepository $statRepo,
        PdfReportService $pdf,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        $roster = $players->findByTeamOrderedByNumber($team);
        $stats = [];
        foreach ($roster as $p) {
            $stats[] = ['player' => $p, 'agg' => $statRepo->aggregateForPlayer($p)];
        }

        $result = $pdf->renderReport('reports/season.html.twig', [
            'team' => $team,
            'roster' => $roster,
            'stats' => $stats,
            'fixtures' => $fixtures->findByTeamOrderedByDate($team),
        ], filename: sprintf('saison-%d.pdf', $team->getId()));

        return new Response($result['body'], 200, [
            'Content-Type' => $result['contentType'],
            'Content-Disposition' => sprintf(
                '%s; filename="saison-%d.%s"',
                'pdf' === $result['mode'] ? 'attachment' : 'inline',
                $team->getId(),
                'pdf' === $result['mode'] ? 'pdf' : 'html',
            ),
        ]);
    }
}
