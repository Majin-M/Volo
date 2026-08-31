<?php

/*
===============================================================================
Contrôleur : ProductController
===============================================================================
Objectif :
    Exposer les endpoints du catalogue produits via l'API REST.

Responsabilités :
    - Lister les produits avec pagination et filtres (GET /api/products).
    - Afficher le détail d'un produit (GET /api/products/{id}).

Routes disponibles :
    - GET /api/products    (Public)
    - GET /api/products/{id} (Public)

Dépendances :
    - ProductService : Logique métier.
    - Request : Pour récupérer les paramètres query string.
===============================================================================
*/

namespace App\Controller;

use App\Security\ProductVoter;
use App\Service\ProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    private ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Liste des produits avec filtres et pagination.
     *
     * Query Params : ?page=1&limit=20&brand=1&skin_concern=acne&available=true
     *
     * @param Request $request Requete HTTP contenant les filtres en query string.
     * @return JsonResponse    Liste paginee des produits.
     */
    #[Route('/api/products', name: 'api_products_list', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        // 1. Récupération des paramètres de requête avec valeurs par défaut
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        
        // Sécurité : on limite le 'limit' à 50 max pour éviter de surcharger la BDD
        if ($limit > 50) $limit = 50;

        // Récupération de tous les autres paramètres (brand, skin_concern, etc.)
        $filters = $request->query->all();

        // 2. Appel au Service
        $result = $this->productService->getPaginatedProducts($filters, $page, $limit);

        // 3. Retour JSON
        return $this->json($result);
    }

    /**
     * Detail d'un produit.
     *
     * @param int $id Identifiant du produit.
     * @return JsonResponse Donnees completes du produit (relations incluses).
     */
    #[Route('/api/products/{id}', name: 'api_product_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $productData = $this->productService->getProductById($id);

        return $this->json([
            'data' => $productData
        ]);
    }

    /**
     * Creation d'un produit (reserve aux administrateurs).
     *
     * @param Request $request Corps JSON avec name, price, brandId (obligatoires).
     * @return JsonResponse    Produit cree (201) ou erreur de validation (400).
     */
    #[Route('/api/products', name: 'api_product_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::CREATE);

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Format JSON invalide.'], 400);
        }

        // Champs obligatoires
        if (empty($data['name']) || empty($data['price']) || empty($data['brandId'])) {
            return $this->json(['error' => 'Les champs name, price et brandId sont obligatoires.'], 400);
        }

        try {
            $product = $this->productService->createProduct($data);

            return $this->json(
                ['data' => $product],
                201,
                [],
                ['groups' => 'product:read']
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Mise a jour d'un produit (reserve aux administrateurs).
     *
     * @param int     $id      Identifiant du produit a modifier.
     * @param Request $request Corps JSON avec les champs a mettre a jour.
     * @return JsonResponse    Produit modifie (200) ou erreur (400/404).
     */
    #[Route('/api/products/{id}', name: 'api_product_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::EDIT);

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Format JSON invalide.'], 400);
        }

        try {
            $product = $this->productService->updateProduct($id, $data);

            return $this->json(
                ['data' => $product],
                200,
                [],
                ['groups' => 'product:read']
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Suppression d'un produit (reserve aux administrateurs).
     *
     * @param int $id Identifiant du produit a supprimer.
     * @return JsonResponse Message de confirmation (200).
     */
    #[Route('/api/products/{id}', name: 'api_product_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::DELETE);

        $this->productService->deleteProduct($id);

        return $this->json(['message' => 'Produit supprime avec succes.']);
    }
}