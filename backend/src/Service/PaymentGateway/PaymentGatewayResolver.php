<?php

/*
===============================================================================
Service : PaymentGatewayResolver
===============================================================================
Objectif :
    Selectionner, parmi les passerelles de paiement disponibles, celle qui
    prend en charge le moyen de paiement demande.

Responsabilites :
    - Recevoir automatiquement toutes les implementations de
      PaymentGatewayInterface presentes dans l'application.
    - Retourner la passerelle correspondant au PaymentMethod fourni.

Dependances :
    - Toute classe taguee 'app.payment_gateway' (via PaymentGatewayInterface).

Used By :
    - PaymentService
===============================================================================
*/

namespace App\Service\PaymentGateway;

use App\Enum\PaymentMethod;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class PaymentGatewayResolver
{
    /**
     * @param iterable<PaymentGatewayInterface> $gateways Passerelles disponibles, injectees automatiquement.
     */
    public function __construct(
        #[TaggedIterator('app.payment_gateway')] private iterable $gateways
    ) {
    }

    /**
     * @param PaymentMethod $method Moyen de paiement demande par le client.
     * @return PaymentGatewayInterface Passerelle correspondante.
     * @throws \InvalidArgumentException Si aucune passerelle ne prend en charge ce moyen de paiement.
     */
    public function resolve(PaymentMethod $method): PaymentGatewayInterface
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->supports($method)) {
                return $gateway;
            }
        }

        throw new \InvalidArgumentException(
            sprintf('Aucune passerelle de paiement disponible pour "%s".', $method->value)
        );
    }
}
