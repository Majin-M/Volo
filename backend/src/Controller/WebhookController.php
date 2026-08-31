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
        * payment_intent.succeeded  -> Paiement capture, Commande payee.
        * payment_intent.payment_failed -> Paiement echoue.
        * Autres -> Ignores (retour 200 pour eviter les retries).
    - Garantir l'idempotence : un evenement deja traite ne modifie rien.
    - Envoyer un email de confirmation apres capture reussie (best-effort).
    - Journaliser chaque etape pour faciliter le debug.

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
    - STRIPE_WEBHOOK_SECRET (env) : Secret HMAC pour valider les signatures.
===============================================================================
*/

namespace App\Controller;

use App\Repository\PaymentRepository;
use App\Service\OrderConfirmationService;
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
            return new JsonResponse(['error' => 'En-tete Stripe-Signature manquant.'], 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch (SignatureVerificationException $e) {
            $this->logger->warning('Webhook Stripe : signature invalide.', [
                'error' => $e->getMessage(),
            ]);
            return new JsonResponse(['error' => 'Signature invalide.'], 400);
        } catch (\UnexpectedValueException $e) {
            $this->logger->warning('Webhook Stripe : payload invalide.', [
                'error' => $e->getMessage(),
            ]);
            return new JsonResponse(['error' => 'Payload invalide.'], 400);
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
     * Met le Payment a CAPTURED et la commande associee a PAID
     * (uniquement si elle est encore PENDING). Envoie ensuite un
     * email de confirmation (best-effort).
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

        if (!$this->paymentStateMachine->can($payment, 'capture')) {
            return new JsonResponse(['message' => 'Deja traite.'], 200);
        }

        $this->paymentStateMachine->apply($payment, 'capture');

        $order = $payment->getOrderEntity();
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
     * Met le Payment a FAILED. La commande reste dans son statut
     * actuel (pas de transition automatique).
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

        if (!$this->paymentStateMachine->can($payment, 'fail')) {
            return new JsonResponse(['message' => 'Deja traite.'], 200);
        }

        $this->paymentStateMachine->apply($payment, 'fail');

        $this->entityManager->flush();

        $this->logger->info('Webhook Stripe : paiement echoue.', [
            'intent_id' => $intentId,
            'payment_id' => $payment->getId(),
        ]);

        return new JsonResponse(['message' => 'Echec enregistre.'], 200);
    }
}
