<?php

/*
===============================================================================
Repository : SkinConcernRepository
===============================================================================
Objectif :
    Centraliser toutes les requêtes liées aux problématiques de peau.

Responsabilités :
    - Récupérer toutes les problématiques.
    - Trouver une problématique par son Slug (pour les URLs).

Exemple d'utilisation :
    $concern = $skinConcernRepository->findOneBySlug('acne');
===============================================================================
*/

namespace App\Repository;

use App\Entity\SkinConcern;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SkinConcern>
 */
class SkinConcernRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SkinConcern::class);
    }

    // Doctrine génère automatiquement findOneBySlug grâce à la propriété dans l'Entité
    // Ajoutons une méthode pour récupérer les concern avec leurs produits si besoin (optimisation)
}