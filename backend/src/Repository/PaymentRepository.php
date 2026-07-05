<?php

/*
===============================================================================
Repository : PaymentRepository
===============================================================================
Objectif :
    Gérer les requêtes liées aux paiements.

Responsabilités :
    - Trouver un paiement associé à une commande spécifique.
    - Mettre à jour le statut d'un paiement suite à un Webhook (Stripe).

Exemple d'utilisation :
    $payment = $paymentRepository->findOneByOrder($order);
===============================================================================
*/

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * Récupère le paiement associé à une commande.
     * (Relation OneToOne, mais encapsulé ici pour abstraction).
     */
    public function findOneByOrder(Order $order): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->where('p.orderEntity = :order')
            ->setParameter('order', $order)
            ->getQuery()
            ->getOneOrNullResult();
    }
}