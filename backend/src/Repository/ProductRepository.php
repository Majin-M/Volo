<?php

/*
===============================================================================
Repository : ProductRepository
===============================================================================
Objectif :
    Centraliser les requêtes complexes du catalogue produits.

Responsabilités :
    - Récupérer les produits avec pagination.
    - Filtrer les produits par Marque (ID).
    - Filtrer les produits par Problématique de peau (Slug).
    - Filtrer par disponibilité (Stock).

Paramètres de filtre :
    - brandId         : Identifiant de la marque (Optionnel).
    - skinConcernSlug : Slug de la problématique (ex: 'acne') (Optionnel).
    - available       : Booléen pour ne voir que les produits en stock (Optionnel).
    - limit / offset  : Pour la pagination.

Exemple d'utilisation :
    $products = $productRepository->findFiltered(1, 'acne', true, 20, 0);
===============================================================================
*/

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Recherche de produits avec filtres dynamiques.
     *
     * @param int|null $brandId         Filtre par ID de marque
     * @param string|null $skinConcernSlug Filtre par slug de problématique
     * @param bool|null $available      Filtre disponibilité
     * @param int $limit                Limite de résultats (pagination)
     * @param int $offset               Décalage (pagination)
     * @return Product[]
     */
    public function findFiltered(
        ?int $brandId = null, 
        ?string $skinConcernSlug = null, 
        ?bool $available = null, 
        int $limit = 20, 
        int $offset = 0
    ): array {
        $qb = $this->createQueryBuilder('p');

        // Jointure avec Marque pour le filtre (Optionnel pour l'affichage)
        $qb->leftJoin('p.brand', 'b')->addSelect('b');

        // Filtre par Marque
        if ($brandId) {
            $qb->andWhere('p.brand = :brandId')
               ->setParameter('brandId', $brandId);
        }

        // Filtre par Problématique de peau (ManyToMany)
        // Il faut joindre la table pivot
        if ($skinConcernSlug) {
            $qb->innerJoin('p.skinConcerns', 'sc')
               ->andWhere('sc.slug = :slug')
               ->setParameter('slug', $skinConcernSlug);
        }

        // Filtre par Disponibilité
        if ($available !== null) {
            $qb->andWhere('p.isAvailable = :available')
               ->setParameter('available', $available);
        }

        // Pagination
        $qb->setMaxResults($limit)
           ->setFirstResult($offset)
           ->orderBy('p.createdAt', 'DESC'); // Les plus récents d'abord

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre total de résultats pour la pagination.
     * Utilise la même logique de filtres que findFiltered.
     */
    public function countFiltered(
        ?int $brandId = null, 
        ?string $skinConcernSlug = null, 
        ?bool $available = null
    ): int {
        $qb = $this->createQueryBuilder('p');
        $qb->select('COUNT(p.id)');

        if ($brandId) {
            $qb->andWhere('p.brand = :brandId')->setParameter('brandId', $brandId);
        }
        if ($skinConcernSlug) {
            $qb->innerJoin('p.skinConcerns', 'sc')
               ->andWhere('sc.slug = :slug')->setParameter('slug', $skinConcernSlug);
        }
        if ($available !== null) {
            $qb->andWhere('p.isAvailable = :available')->setParameter('available', $available);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}