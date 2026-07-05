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
}
