<?php

/*
===============================================================================
Contrôleur : PaymentController
===============================================================================
Objectif :
    Exposer l'endpoint de creation de paiement.

Responsabilites :
    - Valider la requete entrante (commande, moyen de paiement).
    - Deleguer l'initiation du paiement a PaymentService.
    - Formater la reponse JSON.

Routes disponibles :
    - POST /api/payments : Initialiser le paiement d'une commande.

Securite :
    Protege : ROLE_USER
===============================================================================
*/

namespace App\Controller;

use App\Enum\PaymentMethod;
use App\Repository\OrderRepository;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends AbstractController
{
    /**
     * @param OrderRepository $orderRepository Pour recuperer la commande a payer.
     * @param PaymentService $paymentService Orchestre l'initiation du paiement.
     */
    public function __construct(
        private OrderRepository $orderRepository,
        private PaymentService $paymentService,
    ) {
    }

    /**
     * Initialise le paiement pour une commande existante.
     * Le frontend appelle cet endpoint avant d'afficher le formulaire bancaire.
     *
     * @param Request $request Corps attendu : { orderId: int, method?: string }.
     * @return JsonResponse 201 avec paymentId/clientSecret/amount, ou une erreur 400/404/500.
     */
    #[Route('/api/payments', name: 'api_payments_create', methods: ['POST'])]
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $orderId = $data['orderId'] ?? null;
        $methodValue = $data['method'] ?? PaymentMethod::CARD->value;

        if (!$orderId) {
            return new JsonResponse(['error' => 'orderId manquant.'], 400);
        }

        $order = $this->orderRepository->find($orderId);
        if (!$order) {
            return new JsonResponse(['error' => 'Commande non trouvee.'], 404);
        }

        $method = PaymentMethod::tryFrom($methodValue);
        if (!$method) {
            return new JsonResponse(['error' => 'Moyen de paiement invalide.'], 400);
        }

        try {
            $payment = $this->paymentService->initiatePayment($order, $method);

            return new JsonResponse([
                'data' => [
                    'paymentId' => $payment->getId(),
                    'clientSecret' => $payment->getClientSecret(),
                    'amount' => $payment->getAmount(),
                ],
            ], JsonResponse::HTTP_CREATED);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Erreur de paiement : ' . $e->getMessage()], 500);
        }
    }
}
