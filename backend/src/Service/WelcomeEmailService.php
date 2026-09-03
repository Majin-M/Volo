<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class WelcomeEmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire(env: 'MAILER_FROM')] private string $expediteur,
    ) {
    }

    public function sendWelcome(User $user): void
    {
        $userEmail = $user->getEmail();
        if (!$userEmail) {
            return;
        }

        try {
            $email = (new Email())
                ->from($this->expediteur)
                ->to($userEmail)
                ->subject('Bienvenue sur VOLO !')
                ->text($this->buildBody($user));

            $this->mailer->send($email);

            $this->logger->info('Email de bienvenue envoye.', [
                'to' => $userEmail,
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Echec de l\'envoi de l\'email de bienvenue.', [
                'to' => $userEmail,
                'erreur' => $e->getMessage(),
            ]);
        }
    }

    private function buildBody(User $user): string
    {
        $prenom = $user->getFirstName() ?: 'there';

        return sprintf(
            "Bonjour %s,\n\n"
            . "Bienvenue sur VOLO ! Votre compte a ete cree avec succes.\n\n"
            . "Vous pouvez des maintenant :\n"
            . "  - Parcourir notre catalogue de soins\n"
            . "  - Ajouter des produits a votre panier\n"
            . "  - Passer commande en toute securite\n\n"
            . "Si vous avez des questions, n'hesitez pas a nous contacter\n"
            . "via notre formulaire de contact.\n\n"
            . "A bientot sur VOLO !\n",
            $prenom
        );
    }
}
