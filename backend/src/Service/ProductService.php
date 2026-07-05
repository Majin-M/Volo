<?php

/*
===============================================================================
Service : ProductService
===============================================================================
Objectif :
    Centraliser la logique métier liée aux produits.

Responsabilités :
    - Récupérer une liste de produits paginée et filtrée.
    - Récupérer un produit unique par son ID.
    - Lever une exception si un produit n'existe pas (404).

Dépendances :
    - ProductRepository : Pour les requêtes BDD.
    - Serializer (Classe concrète) : Pour transformer les objets en tableau (normalize).

Exceptions :
    - EntityNotFoundException : Si le produit demandé n'existe pas.
===============================================================================
*/

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\SerializerInterface;

class ProductService
{
    private ProductRepository $productRepository;
    private SerializerInterface $serializer; 

    public function __construct(
        ProductRepository $productRepository,
        SerializerInterface $serializer 
    ) {
        $this->productRepository = $productRepository;
        $this->serializer = $serializer;
    }

    /**
     * Récupère une liste de produits selon les filtres.
     *
     * @param array $filters Filtres provenant de la QueryString (brand, skin_concern, available)
     * @param int $page Page actuelle
     * @param int $limit Nombre d'items par page
     * @return array Tableau contenant les données et les métadonnées de pagination
     */
    public function getPaginatedProducts(array $filters, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        // Récupération des filtres depuis le tableau
        $brandId = $filters['brand'] ?? null;
        $skinConcernSlug = $filters['skin_concern'] ?? null;
        // Conversion string 'true'/'false' vers booléen
        $available = isset($filters['available']) ? filter_var($filters['available'], FILTER_VALIDATE_BOOLEAN) : null;

        // Appel au Repository
        $products = $this->productRepository->findFiltered(
            $brandId,
            $skinConcernSlug,
            $available,
            $limit,
            $offset
        );

        // Comptage total pour la pagination
        $total = $this->productRepository->countFiltered(
            $brandId,
            $skinConcernSlug,
            $available
        );

        // Normalisation des données pour l'API (DTO implicite via Serializer Groups)
        // Nous demandons au Serializer de ne renvoyer que les groupes 'product:read'
        $data = $this->serializer->normalize($products, null, ['groups' => 'product:read']);

        return [
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]
        ];
    }

    /**
     * Récupère un produit par son ID.
     *
     * @param int $id
     * @return array Les données du produit normalisées
     * @throws NotFoundHttpException Si le produit n'existe pas
     */
    public function getProductById(int $id): array
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            throw new NotFoundHttpException('Aucun produit trouvé avec l\'identifiant ' . $id . '.');
        }

        // Normalisation pour l'API
        return $this->serializer->normalize($product, null, ['groups' => 'product:read']);
    }
}
