<?php

/*
===============================================================================
Contrôleur : OrderController
===============================================================================
Objectif :
    Gérer les endpoints liés aux commandes clients.

Responsabilités :
    - Créer une nouvelle commande (POST /api/orders).
    - Lister l'historique des commandes de l'utilisateur (GET /api/orders).

Routes disponibles :
    - POST /api/orders    (Protégé : ROLE_USER)
    - GET  /api/orders    (Protégé : ROLE_USER)

Dépendances :
    - OrderService : Logique métier.
    - OrderRepository : Accès aux données (historique).
    - Security : Accès à l'utilisateur connecté via $this->getUser().
===============================================================================
*/

namespace App\Controller;

use App\Http\ApiError;
use App\Http\JsonBody;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Security\OrderVoter;
use App\Service\OrderService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class OrderController extends AbstractController
{
    private OrderService $orderService;
    private OrderRepository $orderRepository;
    private LoggerInterface $logger;

    public function __construct(
        OrderService $orderService,
        OrderRepository $orderRepository,
        LoggerInterface $logger,
    ) {
        $this->orderService = $orderService;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Liste l'historique des commandes de l'utilisateur connecte.
     *
     * @param Request $request Requete HTTP (query params : page, limit).
     * @return JsonResponse    Liste paginee des commandes avec meta.
     */
    #[Route('/api/orders', name: 'api_orders_list', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, $request->query->getInt('page', 1));
        // Plancher autant que plafond : `?limit=-5` descendait jusqu'a Doctrine.
        $limit = max(1, min($request->query->getInt('limit', 20), 100));

        $orders = $this->orderRepository->findByUser($user, $page, $limit);
        $total = $this->orderRepository->countByUser($user);

        return $this->json([
            'data' => $orders,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]
        ], 200, [], ['groups' => 'order:read']);
    }

    /**
     * Creation d'une commande.
     *
     * @param Request $request Corps JSON avec items et shippingAddress.
     * @return JsonResponse    Commande creee (201) ou erreur (400/500).
     */
    #[Route('/api/orders', name: 'api_orders_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->denyAccessUnlessGranted(OrderVoter::CREATE);

        // Objet JSON exige : un scalaire comme `"x"` passait `!$data` puis
        // faisait planter le service en TypeError (500). Cf. App\Http\JsonBody.
        $data = JsonBody::decode($request);

        if ($data === null || $data === []) {
            return ApiError::response('Format JSON invalide.', 400);
        }

        try {
            $order = $this->orderService->createOrder($data, $user);

            return $this->json(
                ['data' => $order], 
                201, 
                [], 
                ['groups' => 'order:read']
            );

        } catch (\InvalidArgumentException $e) {
            return ApiError::response($e->getMessage(), 400);
        } catch (\Exception $e) {
            // On masque la cause au client — un message d'exception peut
            // divulguer la structure interne — mais on la JOURNALISE.
            //
            // Sans cette ligne, $e etait capture puis jamais lu : une creation
            // de commande qui echoue ne laissait aucune trace. Sur le chemin
            // de l'argent, c'est le pire endroit ou perdre l'information :
            // le client voit une erreur, et personne ne peut dire laquelle.
            $this->logger->error('Echec de creation de commande.', [
                'exception' => $e,
                'user' => $this->getUser()?->getUserIdentifier(),
            ]);

            return ApiError::response('Erreur interne du serveur.', 500);
        }
    }
}