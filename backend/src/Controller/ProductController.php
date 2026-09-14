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

use App\Entity\Product;
use App\Http\ApiError;
use App\Http\JsonBody;
use App\Repository\ProductRepository;
use App\Security\ProductVoter;
use App\Service\ProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    private ProductService $productService;
    private ProductRepository $productRepository;

    public function __construct(
        ProductService $productService,
        ProductRepository $productRepository,
    ) {
        $this->productService = $productService;
        $this->productRepository = $productRepository;
    }

    /**
     * Recupere le produit vise par une route, pour le soumettre au Voter.
     *
     * ProductVoter::supports() exige un sujet de type Product sur EDIT et
     * DELETE. Appeler denyAccessUnlessGranted() sans ce sujet fait abstenir
     * le Voter, et une abstention vaut refus : les routes repondaient 403 a
     * tout le monde, administrateurs compris.
     */
    private function findProductOr404(int $id): Product
    {
        $product = $this->productRepository->find($id);

        if ($product === null) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        return $product;
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

        // Les DEUX bornes comptent, pas seulement le plafond.
        //
        // Le plafond a 50 protege la base d'un `?limit=100000`. Mais rien ne
        // gardait le plancher, et le service calcule `($page - 1) * $limit` :
        // `?limit=-5` et `?page=0` produisaient donc des valeurs negatives qui
        // descendaient jusqu'a Doctrine. Une requete malformee doit rendre un
        // resultat vide ou par defaut, jamais une erreur serveur — un 500 sur
        // une entree publique, c'est une surface d'attaque autant qu'un bug.
        $limit = max(1, min($limit, 50));
        $page = max(1, $page);

        // Récupération de tous les autres paramètres (brand, skin_concern, etc.)
        $filters = $request->query->all();

        // 2. Appel au Service
        $result = $this->productService->getPaginatedProducts($filters, $page, $limit);

        // 3. Retour JSON avec cache HTTP
        $response = $this->json($result);
        $etag = md5((string) json_encode($result));
        $response->setEtag($etag);
        $response->setPublic();
        $response->setMaxAge(60);
        $response->headers->set('Cache-Control', 'public, max-age=60, must-revalidate');

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    /**
     * Detail d'un produit.
     *
     * @param int $id Identifiant du produit.
     * @return JsonResponse Donnees completes du produit (relations incluses).
     */
    // {id} contraint a un entier positif de 18 chiffres au plus : `abc` ou un
    // nombre depassant PHP_INT_MAX levaient un TypeError sur `int $id` (500).
    // Ils ne correspondent desormais a aucune route : 404.
    #[Route('/api/products/{id}', name: 'api_product_show', requirements: ['id' => '[1-9]\d{0,17}'], methods: ['GET'])]
    public function show(int $id, Request $request): JsonResponse
    {
        $productData = $this->productService->getProductById($id);

        $response = $this->json(['data' => $productData]);
        $etag = md5((string) json_encode($productData));
        $response->setEtag($etag);
        $response->setPublic();
        $response->setMaxAge(300);
        $response->headers->set('Cache-Control', 'public, max-age=300, must-revalidate');

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
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

        // Objet JSON exige : un scalaire comme `"x"` passait `!$data` puis
        // faisait planter le service en TypeError (500). Cf. App\Http\JsonBody.
        $data = JsonBody::decode($request);

        if ($data === null || $data === []) {
            return ApiError::response('Format JSON invalide.', 400);
        }

        // Champs obligatoires
        if (empty($data['name']) || empty($data['price']) || empty($data['brandId'])) {
            return ApiError::response('Les champs name, price et brandId sont obligatoires.', 400);
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
            return ApiError::response($e->getMessage(), 400);
        }
    }

    /**
     * Mise a jour d'un produit (reserve aux administrateurs).
     *
     * @param int     $id      Identifiant du produit a modifier.
     * @param Request $request Corps JSON avec les champs a mettre a jour.
     * @return JsonResponse    Produit modifie (200) ou erreur (400/404).
     */
    #[Route('/api/products/{id}', name: 'api_product_update', requirements: ['id' => '[1-9]\d{0,17}'], methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::EDIT, $this->findProductOr404($id));

        // Objet JSON exige : un scalaire comme `"x"` passait `!$data` puis
        // faisait planter le service en TypeError (500). Cf. App\Http\JsonBody.
        $data = JsonBody::decode($request);

        if ($data === null || $data === []) {
            return ApiError::response('Format JSON invalide.', 400);
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
            return ApiError::response($e->getMessage(), 400);
        }
    }

    /**
     * Suppression d'un produit (reserve aux administrateurs).
     *
     * @param int $id Identifiant du produit a supprimer.
     * @return JsonResponse Message de confirmation (200).
     */
    #[Route('/api/products/{id}', name: 'api_product_delete', requirements: ['id' => '[1-9]\d{0,17}'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::DELETE, $this->findProductOr404($id));

        $this->productService->deleteProduct($id);

        return $this->json(['message' => 'Produit supprime avec succes.']);
    }
}