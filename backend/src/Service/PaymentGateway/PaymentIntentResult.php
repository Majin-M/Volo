<?php

/*
===============================================================================
DTO : PaymentIntentResult
===============================================================================
Objectif :
    Objet de transfert immuable retourne par toute PaymentGatewayInterface.

Responsabilites :
    - Uniformiser la reponse d'une passerelle de paiement, quel que soit
      le fournisseur (Stripe, PayPal...), pour que le code appelant n'ait
      jamais a connaitre la forme specifique de leurs SDK respectifs.

Proprietes :
    externalId    Identifiant de la transaction chez le fournisseur.
    clientSecret  Secret client transmis au frontend pour confirmer le paiement.
    amount        Montant de la transaction.
===============================================================================
*/

namespace App\Service\PaymentGateway;

final class PaymentIntentResult
{
    /**
     * @param string $externalId Identifiant de la transaction chez le fournisseur.
     * @param string|null $clientSecret Secret client transmis au frontend.
     * @param string $amount Montant de la transaction.
     */
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $clientSecret,
        public readonly string $amount,
    ) {
    }
}
