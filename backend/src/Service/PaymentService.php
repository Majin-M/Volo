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
     * Initialise un paiement pour la commande donnee et persiste l'entite Payment correspondante.
     *
     * @param Order $order Commande a payer.
     * @param PaymentMethod $method Moyen de paiement choisi par le client.
     * @return Payment Entite Payment nouvellement creee, avec le statut PENDING.
     */
    public function initiatePayment(Order $order, PaymentMethod $method): Payment
    {
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
