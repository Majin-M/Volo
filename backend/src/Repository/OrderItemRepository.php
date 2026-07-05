<?php

/*
===============================================================================
Repository : OrderItemRepository
===============================================================================
Objectif :
    Gérer les requêtes sur les lignes de commande.

Responsabilités :
    - Accéder aux détails d'une commande spécifique.
    - (Futur) Calculer les statistiques de vente par produit.

Note Technique :
    Dans la plupart des cas, les items sont accédés via la relation
    $order->getItems(). Ce repository est utile pour les requêtes
    globales (ex: "Quels produits se vendent le mieux ?").
===============================================================================
*/

namespace App\Repository;

use App\Entity\OrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }
}