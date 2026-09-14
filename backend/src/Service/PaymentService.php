<?php

/*
===============================================================================
Service : PaymentService
===============================================================================
Objectif :
    Orchestrer l'initiation d'un paiement pour une commande.

Responsabilites :
    - Resoudre la passerelle de paiement adaptee au moyen choisi.
    - Declencher la creation de la transaction chez le fournisseur.
    - Persister l'entite Payment associee a la commande.
    - Reutiliser le paiement deja ouvert d'une commande, au lieu d'en creer
      un second.

Dependances :
    - PaymentGatewayResolver
    - EntityManagerInterface

Used By :
    - PaymentController
===============================================================================
*/

namespace App\Service;

use App\Entity\Order;
use App\Entity\Payment;
use App\Enum\OrderStatus;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Service\PaymentGateway\PaymentGatewayResolver;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
    /**
     * @param PaymentGatewayResolver $gatewayResolver Selectionne la passerelle adaptee.
     * @param EntityManagerInterface $entityManager Pour persister l'entite Payment.
     */
    public function __construct(
        private PaymentGatewayResolver $gatewayResolver,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Initialise le paiement d'une commande, ou renvoie celui deja ouvert.
     *
     * IDEMPOTENT, et c'est un correctif : chaque appel creait un nouveau
     * Payment. Or `payment.order_id` est unique (une commande, un paiement) :
     * le second appel — un client qui recharge la page apres un refus de
     * carte — echouait en violation de contrainte, et l'API repondait 500.
     * Le client ne pouvait plus payer sa commande. Et chaque tentative
     * laissait chez Stripe un PaymentIntent orphelin.
     *
     * @param Order $order Commande a payer.
     * @param PaymentMethod $method Moyen de paiement choisi par le client.
     * @return Payment Le paiement ouvert de la commande, existant ou cree.
     * @throws \DomainException Si la commande ne peut plus etre payee.
     */
    public function initiatePayment(Order $order, PaymentMethod $method): Payment
    {
        if ($order->getStatus() !== OrderStatus::PENDING) {
            throw new \DomainException('Cette commande ne peut plus etre payee.');
        }

        $existant = $order->getPayment();
        if ($existant !== null) {
            if ($existant->getStatus() === PaymentStatus::PENDING) {
                return $existant;
            }

            throw new \DomainException('Cette commande a deja un paiement finalise.');
        }

        $gateway = $this->gatewayResolver->resolve($method);
        $result = $gateway->createIntent($order);

        $payment = new Payment();
        $payment->setOrderEntity($order);
        $payment->setMethod($method);
        $payment->setStatus(PaymentStatus::PENDING);
        $payment->setClientSecret($result->clientSecret);
        $payment->setAmount($result->amount);
        $payment->setStripePaymentIntentId($result->externalId);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }
}
