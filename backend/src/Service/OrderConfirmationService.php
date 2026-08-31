<?php

/*
===============================================================================
Service : OrderConfirmationService
===============================================================================
Objectif :
    Envoyer un email de confirmation de commande au client apres
    un paiement Stripe capture avec succes.

Responsabilites :
    - Construire le corps de l'email (recapitulatif articles, total,
      adresse de livraison).
    - Envoyer l'email via le composant Mailer de Symfony.
    - Gerer les echecs d'envoi en mode best-effort : un echec est
      journalise mais pas propage (le paiement est deja enregistre).

Dependances :
    - MailerInterface   : Envoi de l'email.
    - LoggerInterface   : Journalisation des succes et erreurs.
    - MAILER_FROM (env) : Adresse expediteur.

Appele par :
    - WebhookController::handlePaymentIntentSucceeded()
===============================================================================
*/

namespace App\Service;

use App\Entity\Order;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
class OrderConfirmationService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire(env: 'MAILER_FROM')] private string $expediteur,
    ) {
    }

    /**
     * Envoie l'email de confirmation au proprietaire de la commande.
     */
    public function sendConfirmation(Order $order): void
    {
        $user = $order->getUser();

        if (!$user) {
            $this->logger->warning('Confirmation de commande : pas d\'utilisateur associe.', [
                'order_id' => $order->getId(),
            ]);
            return;
        }

        $userEmail = $user->getEmail();
        if (!$userEmail) {
            return;
        }

        try {
            $email = (new Email())
                ->from($this->expediteur)
                ->to($userEmail)
                ->subject(sprintf('VOLO — Confirmation de votre commande #%d', $order->getId()))
                ->text($this->buildBody($order, $user));

            $this->mailer->send($email);

            $this->logger->info('Email de confirmation envoye.', [
                'order_id' => $order->getId(),
                'to' => $user->getEmail(),
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Echec de l\'envoi de la confirmation de commande.', [
                'order_id' => $order->getId(),
                'erreur' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Construit le corps texte de l'email de confirmation.
     *
     * Inclut le prenom du client, le recapitulatif des articles avec
     * quantites et sous-totaux, le total general et l'adresse de
     * livraison.
     */
    private function buildBody(Order $order, User $user): string
    {
        $items = $order->getItems();

        $lignes = '';
        foreach ($items as $item) {
            $lignes .= sprintf(
                "  - %s x%d : %.2f EUR\n",
                $item->getProductName(),
                $item->getQuantity(),
                (float) $item->getUnitPrice() * $item->getQuantity()
            );
        }

        return sprintf(
            "Bonjour %s,\n\n"
            . "Merci pour votre commande sur VOLO !\n\n"
            . "Recapitulatif de la commande #%d\n"
            . "--------------------------------------------------\n"
            . "%s"
            . "--------------------------------------------------\n"
            . "Total : %.2f EUR\n\n"
            . "Adresse de livraison :\n"
            . "  %s\n"
            . "  %s %s\n"
            . "  %s\n\n"
            . "Votre paiement a bien ete confirme. Nous preparons votre colis.\n\n"
            . "A bientot sur VOLO !\n",
            $user->getFirstName(),
            $order->getId(),
            $lignes,
            (float) $order->getTotal(),
            $order->getStreet(),
            $order->getPostalCode(),
            $order->getCity(),
            $order->getCountry()
        );
    }
}
