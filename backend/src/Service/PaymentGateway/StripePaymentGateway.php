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
    - Fermer une intention encore ouverte (commande annulee).
    - Rembourser une intention aboutie (paiement recu sur commande annulee).
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
    /**
     * Statuts dans lesquels Stripe accepte d'annuler un PaymentIntent.
     *
     * `requires_payment_method` en fait partie, et c'est le cas qui compte :
     * c'est l'etat d'un paiement apres un REFUS de carte. Stripe le laisse
     * ouvert, le client peut reessayer — d'ou la necessite de le fermer
     * explicitement quand la commande est annulee.
     */
    private const STATUTS_ANNULABLES = [
        'requires_payment_method',
        'requires_confirmation',
        'requires_action',
        'requires_capture',
    ];

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

    /**
     * Ferme le PaymentIntent s'il est encore ouvert.
     *
     * `succeeded` n'est volontairement pas annule ici : Stripe le refuserait,
     * et l'argent est deja encaisse. Le webhook de succes, en constatant que
     * la commande est annulee, emettra le remboursement.
     */
    public function cancelIntent(string $externalId): void
    {
        $intent = $this->stripe->paymentIntents->retrieve($externalId);

        if (!\in_array($intent->status, self::STATUTS_ANNULABLES, true)) {
            return;
        }

        $this->stripe->paymentIntents->cancel($externalId);
    }

    /**
     * Rembourse integralement le PaymentIntent.
     *
     * La cle d'idempotence garantit qu'un webhook rejoue par Stripe — ce qui
     * arrive — ne produit pas un second remboursement : Stripe renvoie le
     * premier au lieu d'en creer un autre.
     */
    public function refund(string $externalId): void
    {
        $this->stripe->refunds->create(
            ['payment_intent' => $externalId],
            ['idempotency_key' => 'volo-refund-' . $externalId],
        );
    }
}
