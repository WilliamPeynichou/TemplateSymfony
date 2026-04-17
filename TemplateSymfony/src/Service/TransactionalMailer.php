<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Service d'envoi d'emails transactionnels (welcome, reset password, invitations).
 *
 * L'expéditeur par défaut est piloté via APP_FROM_EMAIL.
 * En dev on utilisera Mailpit (SMTP sur localhost:1025).
 */
final class TransactionalMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default:default_from_email:APP_FROM_EMAIL)%')]
        private readonly string $fromEmail = 'no-reply@andfield.local',
    ) {
    }

    public function sendWelcome(string $to): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'Andfield'))
            ->to($to)
            ->subject('Bienvenue chez Andfield')
            ->htmlTemplate('email/welcome.html.twig')
            ->textTemplate('email/welcome.txt.twig');

        $this->send($email);
    }

    public function sendPasswordReset(string $to, string $resetLink): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'Andfield'))
            ->to($to)
            ->subject('Réinitialisation de votre mot de passe')
            ->htmlTemplate('email/password_reset.html.twig')
            ->context(['resetLink' => $resetLink]);

        $this->send($email);
    }

    public function sendOrganizationInvitation(string $to, string $orgName, string $acceptLink): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'Andfield'))
            ->to($to)
            ->subject(sprintf('Invitation à rejoindre %s', $orgName))
            ->htmlTemplate('email/organization_invitation.html.twig')
            ->context([
                'orgName' => $orgName,
                'acceptLink' => $acceptLink,
            ]);

        $this->send($email);
    }

    public function sendWeeklyInsight(string $to, string $summaryHtml): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'Andfield'))
            ->to($to)
            ->subject('Votre briefing hebdomadaire')
            ->htmlTemplate('email/weekly_insight.html.twig')
            ->context(['summaryHtml' => $summaryHtml]);

        $this->send($email);
    }

    private function send(TemplatedEmail $email): void
    {
        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('[Mailer] envoi échoué', [
                'to' => implode(',', array_map(fn ($a) => $a->getAddress(), $email->getTo())),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
