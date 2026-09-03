<?php

/*
===============================================================================
Service : StripePaymentGateway
===============================================================================
Objectif :
    Implementation Stripe du contrat PaymentGatewayInterface.

Responsabilites :
    - Creer une intention de paiement (PaymentIntent) aupres de Stripe pour
      une commande donnee.
    - Traduire la reponse Stripe en PaymentIntentResult, independant du SDK.

Dependances :
    - Stripe\StripeClient (instance injectee, configuree avec la cle secrete)

Configuration requise :
    - Variable d'environnement STRIPE_SECRET_KEY.
===============================================================================
*/

namespace App\Service\PaymentGateway;

use App\Entity\Order;
use App\Enum\PaymentMethod;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StripePaymentGateway implements PaymentGatewayInterface
{
    private StripeClient $stripe;

    /**
     * @param string $stripeSecretKey Cle secrete Stripe (STRIPE_SECRET_KEY).
     */
    public function __construct(
        #[Autowire(env: 'STRIPE_SECRET_KEY')] string $stripeSecretKey
    ) {
        $this->stripe = new StripeClient($stripeSecretKey);
    }

    /**
     * @param PaymentMethod $method Moyen de paiement demande par le client.
     * @return bool True uniquement pour PaymentMethod::CARD.
     */
    public function supports(PaymentMethod $method): bool
    {
        return $method === PaymentMethod::CARD;
    }

    /**
     * Cree un PaymentIntent Stripe pour le montant total de la commande.
     *
     * @param Order $order Commande a payer.
     * @return PaymentIntentResult Identifiant Stripe, clientSecret et montant.
     */
    public function createIntent(Order $order): PaymentIntentResult
    {
        $paymentIntent = $this->stripe->paymentIntents->create([
            // Stripe attend un montant en centimes.
            'amount' => (int) round(((float) $order->getTotal()) * 100),
            'currency' => 'eur',
            'metadata' => [
                'order_id' => (string) $order->getId(),
                'order_reference' => $order->getReference(),
            ],
        ]);

        return new PaymentIntentResult(
            externalId: $paymentIntent->id,
            clientSecret: $paymentIntent->client_secret,
            amount: $order->getTotal(),
        );
    }
}
