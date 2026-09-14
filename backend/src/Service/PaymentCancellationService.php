<?php

/*
===============================================================================
Service : PaymentCancellationService
===============================================================================
Objectif :
    Regler le sort du PAIEMENT quand sa commande est annulee, et rembourser
    tout argent recu pour une commande qui n'existe plus.

Le probleme qu'il resout :
    Annuler une commande ne touchait qu'a la base VOLO. Le PaymentIntent
    restait ouvert chez Stripe : le client pouvait encore payer une commande
    annulee. Et quand un paiement aboutissait apres coup, rien ne le
    remboursait — le client etait debite pour rien, sans que personne ne le
    sache. Verifie avec de vrais paiements Stripe le 14/09/2026.

Regle retenue (decision du 14/09/2026) :
    Tout paiement recu pour une commande annulee est REMBOURSE AUTOMATIQUEMENT,
    et l'administrateur en est prevenu par email.

Ne flushe pas : l'appelant maitrise sa transaction, comme pour
OrderService::releaseStock().

Appele par :
    - WebhookController         : paiement abouti sur commande annulee.
    - ReleaseStaleOrdersCommand : annulation des commandes abandonnees.
    - OrderCrudController       : annulation depuis le back-office.
===============================================================================
*/

namespace App\Service;

use App\Entity\Order;
use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Service\PaymentGateway\PaymentGatewayResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Workflow\WorkflowInterface;

class PaymentCancellationService
{
    public function __construct(
        private PaymentGatewayResolver $gatewayResolver,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire(service: 'state_machine.payment')] private WorkflowInterface $paymentStateMachine,
        #[Autowire(env: 'ADMIN_EMAIL')] private string $adminEmail,
        #[Autowire(env: 'MAILER_FROM')] private string $expediteur,
    ) {
    }

    /**
     * Regle le paiement d'une commande que l'on s'apprete a annuler.
     *
     *   - Paiement ouvert  : ferme chez le prestataire, puis marque `failed`.
     *     Un client ne peut plus payer une commande annulee.
     *   - Paiement encaisse : rembourse, puis marque `refunded`, et
     *     l'administrateur est prevenu.
     *   - Aucun paiement, ou deja ferme / rembourse : rien a faire.
     *
     * Une exception du prestataire est propagee : a l'appelant de decider
     * s'il annule quand meme la commande (voir ReleaseStaleOrdersCommand).
     */
    public function settleForCancelledOrder(Order $order): void
    {
        $payment = $order->getPayment();
        if ($payment === null) {
            return;
        }

        if ($payment->getStatus() === PaymentStatus::PENDING) {
            $intentId = $payment->getStripePaymentIntentId();
            if ($intentId !== null) {
                $this->gatewayResolver->resolve($payment->getMethod())->cancelIntent($intentId);
            }
            $this->paymentStateMachine->apply($payment, 'fail');

            return;
        }

        if ($payment->getStatus() === PaymentStatus::CAPTURED) {
            $this->rembourser($payment, $order, 'La commande, deja payee, a ete annulee.');
        }
    }

    /**
     * Rembourse un paiement qui a abouti chez le prestataire alors que sa
     * commande etait deja annulee.
     *
     * Cas reel : le client reessaie sa carte apres un refus, pendant que le
     * balayage des commandes abandonnees annule la sienne.
     */
    public function refundPaymentOnCancelledOrder(Payment $payment, Order $order): void
    {
        $this->rembourser($payment, $order, 'Un paiement a abouti chez Stripe pour une commande deja annulee.');
    }

    private function rembourser(Payment $payment, Order $order, string $motif): void
    {
        $intentId = $payment->getStripePaymentIntentId();
        if ($intentId === null) {
            throw new \LogicException(sprintf(
                'Paiement %d sans identifiant de transaction : remboursement impossible.',
                (int) $payment->getId(),
            ));
        }

        // Le remboursement d'abord, l'etat ensuite : si le prestataire refuse,
        // l'exception remonte et le paiement n'est pas marque rembourse a tort.
        $this->gatewayResolver->resolve($payment->getMethod())->refund($intentId);
        $this->paymentStateMachine->apply($payment, 'refund');

        $this->logger->warning('Paiement rembourse automatiquement.', [
            'order_id' => $order->getId(),
            'payment_id' => $payment->getId(),
            'intent_id' => $intentId,
            'montant' => $payment->getAmount(),
            'motif' => $motif,
        ]);

        $this->notifierAdministrateur($payment, $order, $intentId, $motif);
    }

    /**
     * Best-effort : un echec d'envoi est journalise, jamais propage. Le
     * remboursement a eu lieu, il ne doit pas etre annule faute d'email.
     */
    private function notifierAdministrateur(Payment $payment, Order $order, string $intentId, string $motif): void
    {
        try {
            $email = (new Email())
                ->from($this->expediteur)
                ->to($this->adminEmail)
                ->subject(sprintf('VOLO — Remboursement automatique, commande #%d', (int) $order->getId()))
                ->text(sprintf(
                    "Un remboursement a ete emis automatiquement.\n\n"
                    . "Motif : %s\n\n"
                    . "Commande : #%d (reference %s)\n"
                    . "Client : %s\n"
                    . "Montant rembourse : %s EUR\n"
                    . "Transaction Stripe : %s\n\n"
                    . "Aucune action n'est requise. Le remboursement est visible dans le tableau de bord Stripe ;\n"
                    . "le client le recoit sous 5 a 10 jours ouvres selon sa banque.\n",
                    $motif,
                    (int) $order->getId(),
                    $order->getReference(),
                    $order->getUser()?->getEmail() ?? 'inconnu',
                    $payment->getAmount() ?? '?',
                    $intentId,
                ));

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Remboursement effectue, mais l\'administrateur n\'a pas pu etre prevenu.', [
                'order_id' => $order->getId(),
                'intent_id' => $intentId,
                'erreur' => $e->getMessage(),
            ]);
        }
    }
}
