<?php

/*
===============================================================================
Enum : PaymentStatus
===============================================================================
Objectif :
    Définir les différents états par lesquels passe une transaction financière.
    Permet de suivre le cycle de vie du paiement de la commande.

Valeurs possibles :
    - PENDING  : Paiement initié, en attente de confirmation bancaire (3D Secure...).
    - CAPTURED : Paiement validé, les fonds ont été capturés avec succès.
    - FAILED   : Erreur lors du traitement (fonds insuffisants, refus bancaire...).
    - REFUNDED : Remboursement effectué suite à une annulation, un retour ou un litige.

Utilisation :
    Ce statut est généralement mis à jour via les Webhooks retournés par la
    passerelle de paiement (Stripe/PayPal). Il déclenche ensuite le passage
    de la commande au statut 'PAID' ou 'CANCELLED'.
===============================================================================
*/

namespace App\Enum;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case CAPTURED = 'captured';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
}