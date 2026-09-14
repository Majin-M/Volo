<?php

/*
===============================================================================
Interface : PaymentGatewayInterface
===============================================================================
Objectif :
    Definir le contrat commun a toute passerelle de paiement (Stripe, PayPal...).

Responsabilites :
    - Declarer les operations qu'une passerelle de paiement doit exposer.
    - Servir d'abstraction entre la couche metier (PaymentService) et les
      SDK concrets de paiement.

Pourquoi cancelIntent() et refund() :
    La passerelle ne savait que CREER un paiement. Or une commande annulee
    doit fermer son paiement chez le prestataire, faute de quoi le client
    peut encore payer une commande qui n'existe plus ; et un paiement recu
    pour une commande annulee doit etre rembourse. Sans ces deux operations,
    aucune de ces situations n'avait de reponse.

Implementee par :
    - StripePaymentGateway
    - PayPalPaymentGateway
===============================================================================
*/

namespace App\Service\PaymentGateway;

use App\Entity\Order;
use App\Enum\PaymentMethod;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.payment_gateway')]
interface PaymentGatewayInterface
{
    /**
     * Indique si cette passerelle sait traiter le moyen de paiement donne.
     *
     * @param PaymentMethod $method Moyen de paiement demande par le client.
     * @return bool True si cette passerelle prend en charge ce moyen de paiement.
     */
    public function supports(PaymentMethod $method): bool;

    /**
     * Initialise une transaction de paiement pour la commande donnee.
     *
     * @param Order $order Commande a payer.
     * @return PaymentIntentResult Informations necessaires au frontend pour confirmer le paiement.
     */
    public function createIntent(Order $order): PaymentIntentResult;

    /**
     * Ferme une transaction encore ouverte, pour qu'elle ne puisse plus aboutir.
     *
     * Sans effet si la transaction est deja fermee. Sans effet egalement si
     * elle a deja abouti : l'argent est alors encaisse, et c'est le webhook
     * de succes qui declenchera le remboursement.
     *
     * @param string $externalId Identifiant de la transaction chez le prestataire.
     */
    public function cancelIntent(string $externalId): void;

    /**
     * Rembourse integralement une transaction aboutie.
     *
     * Doit etre idempotent : un webhook rejoue ne doit pas rembourser deux fois.
     *
     * @param string $externalId Identifiant de la transaction chez le prestataire.
     */
    public function refund(string $externalId): void;
}
