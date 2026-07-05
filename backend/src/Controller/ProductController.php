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

use App\Service\ProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    private ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Liste des produits avec filtres et pagination.
     * Query Params: ?page=1&limit=20&brand=1&skin_concern=acne&available=true
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
     * Détail d'un produit.
     */
    #[Route('/api/products/{id}', name: 'api_product_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        // 1. Appel au Service
        $productData = $this->productService->getProductById($id);

        // 2. Formatage de la réponse (Enveloppe 'data')
        return $this->json([
            'data' => $productData
        ]);
    }
}