<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Team;
use App\Repository\PlayerMatchStatRepository;
use App\Repository\PlayerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ExportController extends AbstractController
{
    #[Route('/teams/{id}/export/roster.csv', name: 'app_export_roster', methods: ['GET'])]
    public function roster(Team $team, PlayerRepository $players): Response
    {
        $this->denyAccessUnlessGranted('COACH', $team);

        $list = $players->findByTeamOrderedByNumber($team);

        $response = new StreamedResponse(function () use ($list) {
            $fp = fopen('php://output', 'wb');
            fputcsv($fp, ['id', 'numero', 'prenom', 'nom', 'position', 'pied_fort', 'date_naissance']);
            foreach ($list as $p) {
                fputcsv($fp, [
                    $p->getId(),
                    $p->getJerseyNumber(),
                    $p->getFirstName(),
                    $p->getLastName(),
                    $p->getPosition(),
                    $p->getStrongFoot(),
                    $p->getBirthdate()?->format('Y-m-d'),
                ]);
            }
            fclose($fp);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="roster-%d.csv"', $team->getId()));

        return $response;
    }

    #[Route('/teams/{id}/export/stats.csv', name: 'app_export_stats', methods: ['GET'])]
    public function stats(
        Team $team,
        PlayerRepository $players,
        PlayerMatchStatRepository $statRepo,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        $list = $players->findByTeamOrderedByNumber($team);

        $response = new StreamedResponse(function () use ($list, $statRepo) {
            $fp = fopen('php://output', 'wb');
            fputcsv($fp, ['joueur', 'matches', 'minutes', 'buts', 'passes_decisives', 'jaunes', 'rouges', 'note_moyenne']);
            foreach ($list as $p) {
                $agg = $statRepo->aggregateForPlayer($p);
                fputcsv($fp, [
                    $p->getFirstName().' '.$p->getLastName(),
                    $agg['matches'],
                    $agg['minutes'],
                    $agg['goals'],
                    $agg['assists'],
                    $agg['yellow'],
                    $agg['red'],
                    $agg['avgRating'] ?? '',
                ]);
            }
            fclose($fp);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="stats-%d.csv"', $team->getId()));

        return $response;
    }
}
