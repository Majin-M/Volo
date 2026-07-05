<?php

/*
===============================================================================
Enum : OrderStatus
===============================================================================
Objectif :
    Représenter le cycle de vie d'une commande.
    Assure que seuls des statuts valides et cohérents sont stockés en base.

Valeurs possibles :
    - PENDING   : Commande créée, en attente de paiement.
    - PAID      : Paiement validé, en attente de préparation.
    - SHIPPED   : Commande expédiée au client.
    - DELIVERED : Commande réceptionnée par le client.
    - CANCELLED : Commande annulée (par client ou admin).

Utilisation :
    Ce statut déclenche les notifications (emails) et les actions métier
    (mise à jour du stock, facturation).
===============================================================================
*/

namespace App\Enum;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
}