<?php

/*
===============================================================================
Repository : BrandRepository
===============================================================================
Objectif :
    Centraliser toutes les requêtes liées à l'entité Brand (Marque).

Responsabilités :
    - Récupérer la liste des marques.
    - Trier les marques par ordre alphabétique.

Exemple d'utilisation :
    $brands = $brandRepository->findAllSortedByName();
===============================================================================
*/

namespace App\Repository;

use App\Entity\Brand;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Brand>
 */
class BrandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Brand::class);
    }

    /**
     * Récupère toutes les marques triées par nom.
     *
     * @return Brand[] Retourne un tableau d'objets Brand
     */
    public function findAllSortedByName(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}