<?php

/*
===============================================================================
Repository : RoutineRepository
===============================================================================
Objectif :
    Gérer les requêtes complexes sur les routines de soins.

Responsabilités :
    - Filtrer les routines par niveau (débutant, expert).
    - Filtrer les routines par problématique de peau (via les produits associés).

Note Technique :
    Comme l'API demande de filtrer par skin_concern, il faut faire une jointure
    entre Routine -> Product -> SkinConcern.
===============================================================================
*/

namespace App\Repository;

use App\Entity\Routine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Routine>
 */
class RoutineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Routine::class);
    }

    /**
     * Recherche des routines selon des filtres optionnels.
     *
     * @param string|null $level Niveau de la routine (Enum)
     * @param string|null $skinConcernSlug Slug de la problématique
     * @return Routine[]
     */
    public function findByFilters(?string $level = null, ?string $skinConcernSlug = null): array
    {
        $qb = $this->createQueryBuilder('r');

        // Filtre par niveau de routine
        if ($level) {
            $qb->andWhere('r.level = :level')
               ->setParameter('level', $level);
        }

        // Filtre par problématique de peau
        // On doit rejoindre les produits, puis les problématiques liées aux produits
        if ($skinConcernSlug) {
            $qb->innerJoin('r.products', 'p')
               ->innerJoin('p.skinConcerns', 'sc')
               ->andWhere('sc.slug = :slug')
               ->setParameter('slug', $skinConcernSlug);
        }

        return $qb->orderBy('r.name', 'ASC')
                  ->getQuery()
                  ->getResult();
    }
}