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
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class PaymentGatewayResolver
{
    /**
     * AutowireIterator et non TaggedIterator : ce dernier est deprecie depuis
     * symfony/dependency-injection 7.1. Le comportement est identique, seul le
     * nom change.
     *
     * Ce n'etait pas cosmetique : la depreciation n'est levee qu'a la
     * compilation du conteneur, donc uniquement a cache froid. PHPUnit 13
     * comptant les depreciations comme des echecs, la suite sortait en exit 1
     * au premier lancement et en exit 0 aux suivants — verte ou rouge selon
     * l'etat du cache, et rouge en permanence sur une CI qui part de zero.
     *
     * @param iterable<PaymentGatewayInterface> $gateways Passerelles disponibles, injectees automatiquement.
     */
    public function __construct(
        #[AutowireIterator('app.payment_gateway')] private iterable $gateways
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
