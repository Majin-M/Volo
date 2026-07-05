<?php

/*
===============================================================================
Service : PayPalPaymentGateway
===============================================================================
Objectif :
    Implementation PayPal du contrat PaymentGatewayInterface.

Responsabilites :
    - Creer une intention de paiement aupres de PayPal pour une commande donnee.

Statut :
    Non implementee. L'integration du SDK PayPal (Orders API v2) reste a faire ;
    ajouter PAYPAL_CLIENT_ID et PAYPAL_CLIENT_SECRET au .env le moment venu.

Dependances :
    - A definir lors de l'integration du SDK PayPal.
===============================================================================
*/

namespace App\Service\PaymentGateway;

use App\Entity\Order;
use App\Enum\PaymentMethod;

class PayPalPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @param PaymentMethod $method Moyen de paiement demande par le client.
     * @return bool True uniquement pour PaymentMethod::PAYPAL.
     */
    public function supports(PaymentMethod $method): bool
    {
        return $method === PaymentMethod::PAYPAL;
    }

    /**
     * @param Order $order Commande a payer.
     * @return PaymentIntentResult
     * @throws \RuntimeException Tant que l'integration PayPal n'est pas implementee.
     */
    public function createIntent(Order $order): PaymentIntentResult
    {
        throw new \RuntimeException('Paiement PayPal non encore implemente.');
    }
}
