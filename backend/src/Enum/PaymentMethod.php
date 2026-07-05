<?php

/*
===============================================================================
Enum : PaymentMethod
===============================================================================
Objectif :
    Lister les moyens de paiement acceptés et configurés sur la plateforme VOLO.

Valeurs possibles :
    - CARD   : Paiement par carte bancaire (Visa, Mastercard, American Express).
    - PAYPAL : Paiement via le compte PayPal (connecté).

Utilisation :
    Utilisé par le PaymentService pour router la requête vers le bon fournisseur
    (API Stripe pour Card, API PayPal pour Paypal).
    Détermine également l'icône/le libellé affiché dans le formulaire de checkout.
===============================================================================
*/

namespace App\Enum;

enum PaymentMethod: string
{
    case CARD = 'card';
    case PAYPAL = 'paypal';
}