<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Team;
use App\Entity\TrainingSession;
use App\Entity\User;
use App\Repository\FixtureRepository;
use App\Repository\TrainingSessionRepository;
use App\Service\IcsExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class CalendarController extends AbstractController
{
    #[Route('/teams/{id}/calendar', name: 'app_team_calendar', methods: ['GET'])]
    public function show(
        Team $team,
        FixtureRepository $fixtures,
        TrainingSessionRepository $trainings,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        return $this->render('calendar/show.html.twig', [
            'team' => $team,
            'fixtures' => $fixtures->findByTeamOrderedByDate($team),
            'trainings' => $trainings->findByTeamOrderedByDate($team),
        ]);
    }

    #[Route('/teams/{id}/trainings/new', name: 'app_training_new', methods: ['GET', 'POST'])]
    public function newTraining(
        Team $team,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        if ($request->isMethod('POST')) {
            /** @var User $user */
            $user = $this->getUser();

            $session = (new TrainingSession())
                ->setTeam($team)
                ->setCoach($user)
                ->setTitle((string) $request->request->get('title'))
                ->setStartsAt(new \DateTimeImmutable((string) $request->request->get('starts_at')))
                ->setDurationMinutes((int) $request->request->get('duration_minutes', 60))
                ->setLocation($request->request->get('location') ?: null)
                ->setFocus($request->request->get('focus') ?: null)
                ->setPlan($request->request->get('plan') ?: null);

            $em->persist($session);
            $em->flush();
            $this->addFlash('success', 'Séance ajoutée.');

            return $this->redirectToRoute('app_team_calendar', ['id' => $team->getId()]);
        }

        return $this->render('calendar/new_training.html.twig', ['team' => $team]);
    }

    #[Route('/teams/{id}/calendar.ics', name: 'app_team_calendar_ics', methods: ['GET'])]
    public function ics(
        Team $team,
        FixtureRepository $fixtures,
        TrainingSessionRepository $trainings,
        IcsExporter $exporter,
    ): Response {
        $this->denyAccessUnlessGranted('COACH', $team);

        $body = $exporter->export(
            $fixtures->findByTeamOrderedByDate($team),
            $trainings->findByTeamOrderedByDate($team),
            calendarName: 'Andfield — '.$team->getName(),
        );

        return new Response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="team-%d.ics"', $team->getId()),
        ]);
    }
}
