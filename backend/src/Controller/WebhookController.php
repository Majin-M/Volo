<?php

/*
===============================================================================
Controleur : WebhookController
===============================================================================
Objectif :
    Recevoir et traiter les evenements Stripe envoyes via webhook.

Responsabilites :
    - Verifier l'authenticite de chaque requete grace a la signature
      HMAC fournie par Stripe (header Stripe-Signature).
    - Dispatcher les evenements selon leur type :
        * payment_intent.succeeded      -> Paiement capture, Commande payee.
                                           Commande deja annulee : REMBOURSEMENT.
        * payment_intent.payment_failed -> Journalise. AUCUN changement d'etat.
        * Autres -> Ignores (retour 200 pour eviter les retries).
    - Garantir l'idempotence : un evenement deja traite ne modifie rien.
    - Envoyer un email de confirmation apres capture reussie (best-effort).
    - Journaliser chaque etape pour faciliter le debug.

Ce qui a change le 14/09/2026, et pourquoi :
    Un refus de carte etait traite comme DEFINITIF : le paiement passait a
    `failed` et le stock etait restitue. Or chez Stripe, payment_failed
    laisse le PaymentIntent ouvert — le client corrige sa carte et reessaie
    sur le meme intent, c'est exactement ce que fait PaymentForm.jsx. Le
    succes qui suivait etait alors ignore (`failed` ne pouvait plus etre
    capture). Constate avec de vrais paiements Stripe :
      - client debite de 30 EUR, commande restee `pending` ;
      - stock restitue alors que l'article etait vendu ;
      - une heure plus tard, le balayage annulait cette commande payee et
        restituait le stock UNE SECONDE FOIS.

Securite :
    - Exempte du controle CSRF (appel serveur-a-serveur Stripe).
    - Exempte d'authentification (PUBLIC_ACCESS dans security.yaml).
    - La verification de signature HMAC remplace ces deux mecanismes.

Routes disponibles :
    - POST /api/webhooks/stripe  (Public — verifie par signature)

Dependances :
    - PaymentRepository           : Retrouver le paiement par intent ID.
    - EntityManagerInterface      : Persister les changements de statut.
    - LoggerInterface             : Journalisation.
    - OrderConfirmationService    : Email de confirmation.
    - PaymentCancellationService  : Remboursement d'un paiement sur commande annulee.
    - STRIPE_WEBHOOK_SECRET (env) : Secret HMAC pour valider les signatures.
===============================================================================
*/

namespace App\Controller;

use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Http\ApiError;
use App\Repository\PaymentRepository;
use App\Service\OrderConfirmationService;
use App\Service\PaymentCancellationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

class WebhookController extends AbstractController
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private OrderConfirmationService $orderConfirmationService,
        private PaymentCancellationService $paymentCancellationService,
        #[Autowire(env: 'STRIPE_WEBHOOK_SECRET')] private string $webhookSecret,
        #[Autowire(service: 'state_machine.order')] private WorkflowInterface $orderStateMachine,
        #[Autowire(service: 'state_machine.payment')] private WorkflowInterface $paymentStateMachine,
    ) {
    }

    /**
     * Point d'entree du webhook Stripe.
     *
     * Verifie la signature, parse l'evenement et dispatche vers le
     * handler adapte. Retourne toujours un code 2xx/4xx pour que
     * Stripe ne retente pas indefiniment.
     */
    #[Route('/api/webhooks/stripe', name: 'api_webhooks_stripe', methods: ['POST'])]
    public function handleStripeWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        if (!$sigHeader) {
            return ApiError::response('En-tete Stripe-Signature manquant.', 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch (SignatureVerificationException $e) {
            $this->logger->warning('Webhook Stripe : signature invalide.', [
                'error' => $e->getMessage(),
            ]);
            return ApiError::response('Signature invalide.', 400);
        } catch (\UnexpectedValueException $e) {
            $this->logger->warning('Webhook Stripe : payload invalide.', [
                'error' => $e->getMessage(),
            ]);
            return ApiError::response('Payload invalide.', 400);
        }

        return match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event),
            default => new JsonResponse(['message' => 'Evenement ignore.'], 200),
        };
    }

    /**
     * Traite un evenement payment_intent.succeeded.
     *
     *   - Deja capture ou rembourse : rien (idempotence, Stripe rejoue).
     *   - Commande ANNULEE : l'argent est rembourse et l'administrateur
     *     prevenu. Une exception du prestataire remonte en 500, et Stripe
     *     rejouera l'evenement plus tard — c'est le comportement voulu.
     *   - Sinon : paiement capture, commande payee si elle est encore
     *     `pending`, email de confirmation.
     */
    private function handlePaymentIntentSucceeded(object $event): JsonResponse
    {
        $intentId = $event->data->object->id;
        $payment = $this->paymentRepository->findOneByStripePaymentIntentId($intentId);

        if (!$payment) {
            $this->logger->error('Webhook Stripe : aucun paiement pour intent.', [
                'intent_id' => $intentId,
            ]);
            return new JsonResponse(['message' => 'Paiement inconnu, ignore.'], 200);
        }

        if (\in_array($payment->getStatus(), [PaymentStatus::CAPTURED, PaymentStatus::REFUNDED], true)) {
            return new JsonResponse(['message' => 'Deja traite.'], 200);
        }

        $order = $payment->getOrderEntity();

        if ($order !== null && $order->getStatus() === OrderStatus::CANCELLED) {
            $this->paymentCancellationService->refundPaymentOnCancelledOrder($payment, $order);
            $this->entityManager->flush();

            $this->logger->warning('Webhook Stripe : paiement recu pour une commande annulee, rembourse.', [
                'intent_id' => $intentId,
                'payment_id' => $payment->getId(),
                'order_id' => $order->getId(),
            ]);

            return new JsonResponse(['message' => 'Paiement rembourse : commande annulee.'], 200);
        }

        if (!$this->paymentStateMachine->can($payment, 'capture')) {
            return new JsonResponse(['message' => 'Deja traite.'], 200);
        }

        $this->paymentStateMachine->apply($payment, 'capture');

        if ($order && $this->orderStateMachine->can($order, 'pay')) {
            $this->orderStateMachine->apply($order, 'pay');
        }

        $this->entityManager->flush();

        $this->logger->info('Webhook Stripe : paiement capture.', [
            'intent_id' => $intentId,
            'payment_id' => $payment->getId(),
            'order_id' => $order?->getId(),
        ]);

        // Email de confirmation (best-effort, echec non bloquant)
        if ($order) {
            $this->orderConfirmationService->sendConfirmation($order);
        }

        return new JsonResponse(['message' => 'Paiement capture.'], 200);
    }

    /**
     * Traite un evenement payment_intent.payment_failed.
     *
     * NE MODIFIE RIEN, deliberement. Un refus de carte n'est pas un echec
     * definitif : Stripe laisse le PaymentIntent ouvert et le client peut
     * reessayer. Le stock reste donc reserve pendant qu'il le fait.
     *
     * Ce qui ferme reellement un paiement, c'est l'annulation de sa
     * commande — par le balayage des paniers abandonnes
     * (app:release-stale-orders) ou par le back-office. C'est a ce moment,
     * et seulement a ce moment, que le stock est restitue.
     */
    private function handlePaymentIntentFailed(object $event): JsonResponse
    {
        $intentId = $event->data->object->id;
        $payment = $this->paymentRepository->findOneByStripePaymentIntentId($intentId);

        if (!$payment) {
            $this->logger->error('Webhook Stripe : aucun paiement pour intent.', [
                'intent_id' => $intentId,
            ]);
            return new JsonResponse(['message' => 'Paiement inconnu, ignore.'], 200);
        }

        $this->logger->info('Webhook Stripe : paiement refuse, transaction toujours ouverte.', [
            'intent_id' => $intentId,
            'payment_id' => $payment->getId(),
            'order_id' => $payment->getOrderEntity()?->getId(),
        ]);

        return new JsonResponse(['message' => 'Refus enregistre : paiement toujours ouvert.'], 200);
    }
}
