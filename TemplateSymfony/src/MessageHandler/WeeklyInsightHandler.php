<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\WeeklyInsightMessage;
use App\Repository\FixtureRepository;
use App\Repository\TeamRepository;
use App\Service\TransactionalMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Construit et envoie un briefing hebdomadaire aux coachs actifs.
 *
 * Version MVP : on renvoie un résumé basique (nb matchs à venir, nb joueurs).
 * À enrichir en phase 3 avec l'agent IA (synthèse naturelle).
 */
#[AsMessageHandler]
final class WeeklyInsightHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TeamRepository $teams,
        private readonly FixtureRepository $fixtures,
        private readonly TransactionalMailer $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(WeeklyInsightMessage $msg): void
    {
        $user = $this->em->find(User::class, $msg->userId);
        if (!$user) {
            $this->logger->warning('[WeeklyInsight] Utilisateur introuvable', ['id' => $msg->userId]);

            return;
        }

        $userTeams = $this->teams->findBy(['coach' => $user]);
        if (!$userTeams) {
            return;
        }

        $bullets = [];
        foreach ($userTeams as $team) {
            $upcoming = $this->fixtures->findUpcomingForTeam($team, 3);
            if ($upcoming) {
                $lines = array_map(
                    fn ($f) => sprintf('%s — vs %s', $f->getMatchDate()->format('d/m'), $f->getOpponent()),
                    $upcoming,
                );
                $bullets[] = sprintf(
                    '<li><strong>%s</strong> : %s</li>',
                    htmlspecialchars($team->getName()),
                    implode(', ', array_map('htmlspecialchars', $lines)),
                );
            }
        }

        $html = $bullets
            ? '<ul>'.implode('', $bullets).'</ul>'
            : '<p>Aucun match planifié cette semaine. Profitez-en pour ajouter une séance !</p>';

        $email = $user->getEmail();
        if (null !== $email) {
            $this->mailer->sendWeeklyInsight($email, $html);
        }
    }
}
