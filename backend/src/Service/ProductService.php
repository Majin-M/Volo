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
use App\Repository\BrandRepository;
use App\Repository\ProductRepository;
use App\Repository\SkinConcernRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\SerializerInterface;

class ProductService
{
    private ProductRepository $productRepository;
    private BrandRepository $brandRepository;
    private SkinConcernRepository $skinConcernRepository;
    private EntityManagerInterface $em;
    private SerializerInterface $serializer;

    public function __construct(
        ProductRepository $productRepository,
        BrandRepository $brandRepository,
        SkinConcernRepository $skinConcernRepository,
        EntityManagerInterface $em,
        SerializerInterface $serializer
    ) {
        $this->productRepository = $productRepository;
        $this->brandRepository = $brandRepository;
        $this->skinConcernRepository = $skinConcernRepository;
        $this->em = $em;
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

    /**
     * Cree un nouveau produit.
     *
     * @param array<string, mixed> $data Donnees du produit (name, price, description, brandId, skinConcernIds, isAvailable)
     * @return Product
     */
    public function createProduct(array $data): Product
    {
        $product = new Product();
        $this->hydrateProduct($product, $data);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    /**
     * Met a jour un produit existant.
     *
     * @param array<string, mixed> $data
     */
    public function updateProduct(int $id, array $data): Product
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            throw new NotFoundHttpException('Aucun produit trouve avec l\'identifiant ' . $id . '.');
        }

        $this->hydrateProduct($product, $data);
        $this->em->flush();

        return $product;
    }

    /**
     * Supprime un produit.
     */
    public function deleteProduct(int $id): void
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            throw new NotFoundHttpException('Aucun produit trouve avec l\'identifiant ' . $id . '.');
        }

        $this->em->remove($product);
        $this->em->flush();
    }

    /**
     * Hydrate un produit a partir des donnees fournies.
     *
     * @param array<string, mixed> $data
     */
    private function hydrateProduct(Product $product, array $data): void
    {
        if (isset($data['name']) && is_string($data['name'])) {
            $product->setName($data['name']);
        }

        if (isset($data['description']) && is_string($data['description'])) {
            $product->setDescription($data['description']);
        }

        if (isset($data['price'])) {
            $product->setPrice((string) (float) $data['price']);
        }

        if (array_key_exists('isAvailable', $data)) {
            $product->setIsAvailable((bool) $data['isAvailable']);
        }

        if (isset($data['brandId'])) {
            $brandId = (int) $data['brandId'];
            $brand = $this->brandRepository->find($brandId);
            if (!$brand) {
                throw new \InvalidArgumentException('Marque introuvable (id: ' . $brandId . ').');
            }
            $product->setBrand($brand);
        }

        if (isset($data['skinConcernIds']) && is_array($data['skinConcernIds'])) {
            foreach ($product->getSkinConcerns()->toArray() as $sc) {
                $product->removeSkinConcern($sc);
            }
            /** @var int|string $scId */
            foreach ($data['skinConcernIds'] as $scId) {
                $sc = $this->skinConcernRepository->find((int) $scId);
                if ($sc) {
                    $product->addSkinConcern($sc);
                }
            }
        }
    }
}
