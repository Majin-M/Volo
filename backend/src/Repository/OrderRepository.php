<?php

/*
===============================================================================
Repository : OrderRepository
===============================================================================
Objectif :
    Centraliser les requêtes liées aux commandes clients.

Responsabilités :
    - Récupérer l'historique des commandes d'un utilisateur connecté.
    - Récupérer les détails d'une commande spécifique.

Exemple d'utilisation :
    $orders = $orderRepository->findByUser($currentUser);
===============================================================================
*/

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

        /**
     * Récupère les commandes d'un utilisateur avec pagination.
     *
     * @param User $user
     * @param int $page
     * @param int $limit
     * @return Order[]
     */
    public function findByUser(User $user, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Commandes restees impayees au-dela d'une date limite.
     *
     * Sert a lever les reservations de stock des paniers abandonnes : le
     * stock est retire des la creation de la commande, rien ne le restitue
     * si le client ne paie jamais (cf. app:release-stale-orders).
     *
     * @return Order[] Les plus anciennes d'abord.
     */
    public function findStalePending(\DateTimeInterface $limite): array
    {
        // getResult() est typee `mixed` : l'extension phpstan-doctrine n'est
        // pas installee, PHPStan ne peut donc pas deduire le type des
        // entites renvoyees. On declare ce que la requete produit reellement.
        /** @var Order[] $commandes */
        $commandes = $this->createQueryBuilder('o')
            ->where('o.status = :statut')
            ->andWhere('o.createdAt < :limite')
            ->setParameter('statut', OrderStatus::PENDING)
            ->setParameter('limite', $limite)
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $commandes;
    }

    /**
     * Compte le nombre total de commandes d'un utilisateur (pour la pagination).
     */
    public function countByUser(User $user): int
    {
        return $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}