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

use App\Http\JsonBody;
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

        // Filtres : types verifies. `?brand=abc` ou `?brand[]=1` atteignaient
        // findFiltered(?int) et levaient un TypeError (500). Un filtre invalide
        // ne peut correspondre a aucun produit : liste vide, pas d'erreur.
        $vide = ['data' => [], 'meta' => ['page' => $page, 'limit' => $limit, 'total' => 0]];

        $brandId = null;
        if (isset($filters['brand']) && $filters['brand'] !== '') {
            $brandId = JsonBody::int($filters['brand']);
            if ($brandId === null) {
                return $vide;
            }
        }

        $skinConcernSlug = null;
        if (isset($filters['skin_concern']) && $filters['skin_concern'] !== '') {
            $skinConcernSlug = JsonBody::string($filters['skin_concern']);
            if ($skinConcernSlug === null) {
                return $vide;
            }
        }
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
     * @param array<mixed> $data Donnees du produit (name, price, description, brandId, skinConcernIds, isAvailable)
     * @return Product
     */
    public function createProduct(array $data): Product
    {
        // Un produit sans nom, prix ou marque ne peut pas etre enregistre : on le
        // dit ici en 400 plutot que de laisser MySQL le refuser en 500.
        foreach (['name', 'price', 'brandId'] as $champ) {
            if (!array_key_exists($champ, $data)) {
                throw new \InvalidArgumentException(sprintf('Le champ %s est obligatoire.', $champ));
            }
        }

        $product = new Product();
        $this->hydrateProduct($product, $data);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    /**
     * Met a jour un produit existant.
     *
     * @param array<mixed> $data
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
     * @param array<mixed> $data
     */
    private function hydrateProduct(Product $product, array $data): void
    {
        // Validation stricte des types ET des limites de la base, AVANT toute
        // ecriture. Audit du 14/09/2026 : un nom numerique n'etait pas applique
        // (nom NULL -> contrainte NOT NULL), et un nom de 100 000 caracteres, un
        // prix de 1e308 ou un stock hors limites atteignaient MySQL, qui refusait
        // l'ecriture. L'API repondait 500 la ou la faute du client meritait 400.
        if (array_key_exists('name', $data)) {
            $nom = JsonBody::string($data['name']);
            if ($nom === null || trim($nom) === '') {
                throw new \InvalidArgumentException('Le nom doit etre une chaine non vide.');
            }
            if (mb_strlen($nom) > 255) {
                throw new \InvalidArgumentException('Le nom est trop long (255 caracteres maximum).');
            }
            $product->setName(trim($nom));
        }

        if (array_key_exists('description', $data)) {
            $description = $data['description'] === null ? null : JsonBody::string($data['description']);
            if ($data['description'] !== null && $description === null) {
                throw new \InvalidArgumentException('La description doit etre une chaine de caracteres.');
            }
            $product->setDescription($description);
        }

        if (array_key_exists('price', $data)) {
            $prix = $data['price'];
            if (!(is_int($prix) || is_float($prix) || (is_string($prix) && is_numeric($prix)))) {
                throw new \InvalidArgumentException('Le prix doit etre un nombre.');
            }
            $valeur = (float) $prix;
            // Colonne DECIMAL(10,2) : 99 999 999,99 au plus.
            if (!is_finite($valeur) || $valeur <= 0 || $valeur > 99999999.99) {
                throw new \InvalidArgumentException('Le prix doit etre compris entre 0,01 et 99 999 999,99.');
            }
            $product->setPrice(number_format($valeur, 2, '.', ''));
        }

        if (array_key_exists('isAvailable', $data)) {
            // Booleen strict : `(bool) "false"` vaut true, ce qui publiait un
            // produit qu'on voulait masquer.
            if (!is_bool($data['isAvailable'])) {
                throw new \InvalidArgumentException('isAvailable doit etre un booleen (true ou false).');
            }
            $product->setIsAvailable($data['isAvailable']);
        }

        if (array_key_exists('stock', $data)) {
            $stock = JsonBody::int($data['stock']);
            // Colonne INT signee : 2 147 483 647 au plus.
            if ($stock === null || $stock < 0 || $stock > 2147483647) {
                throw new \InvalidArgumentException('Le stock doit etre un entier compris entre 0 et 2 147 483 647.');
            }
            $product->setStock($stock);
        }

        if (array_key_exists('brandId', $data)) {
            $brandId = JsonBody::int($data['brandId']);
            $brand = $brandId !== null && $brandId > 0 ? $this->brandRepository->find($brandId) : null;
            if (!$brand) {
                throw new \InvalidArgumentException('Marque introuvable.');
            }
            $product->setBrand($brand);
        }

        if (isset($data['skinConcernIds']) && is_array($data['skinConcernIds'])) {
            foreach ($product->getSkinConcerns()->toArray() as $sc) {
                $product->removeSkinConcern($sc);
            }
            foreach ($data['skinConcernIds'] as $scId) {
                $idProblematique = JsonBody::int($scId);
                $sc = $idProblematique !== null && $idProblematique > 0 ? $this->skinConcernRepository->find($idProblematique) : null;
                if ($sc) {
                    $product->addSkinConcern($sc);
                }
            }
        }
    }
}
