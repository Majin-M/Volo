<?php

/*
===============================================================================
Repository : ContactMessageRepository
===============================================================================
Objectif :
    Gérer les requêtes sur les messages de contact.

Responsabilités :
    - Récupérer les messages non traités pour l'admin.
    - Marquer un message comme traité (via l'entité).

Exemple d'utilisation :
    $messages = $contactMessageRepository->findUnprocessed();
===============================================================================
*/

namespace App\Repository;

use App\Entity\ContactMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactMessage>
 */
class ContactMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactMessage::class);
    }

    /**
     * Récupère la liste des messages n'ayant pas encore été traités.
     * Utile pour le dashboard administration.
     *
     * @return ContactMessage[]
     */
    public function findUnprocessed(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isProcessed = false')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}