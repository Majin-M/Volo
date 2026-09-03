<?php

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Entity\Payment;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsDoctrineListener(event: Events::preUpdate)]
class StatusTransitionSubscriber
{
    public function __construct(
        #[Autowire(service: 'state_machine.order')] private WorkflowInterface $orderStateMachine,
        #[Autowire(service: 'state_machine.payment')] private WorkflowInterface $paymentStateMachine,
    ) {
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $entity = $event->getObject();

        if ($entity instanceof Order && $event->hasChangedField('status')) {
            $from = $this->extractStatusValue($event->getOldValue('status'));
            $to = $this->extractStatusValue($event->getNewValue('status'));
            if ($from !== null && $to !== null) {
                $this->validateTransition($this->orderStateMachine, $from, $to, 'commande');
            }
        }

        if ($entity instanceof Payment && $event->hasChangedField('status')) {
            $from = $this->extractStatusValue($event->getOldValue('status'));
            $to = $this->extractStatusValue($event->getNewValue('status'));
            if ($from !== null && $to !== null) {
                $this->validateTransition($this->paymentStateMachine, $from, $to, 'paiement');
            }
        }
    }

    private function extractStatusValue(mixed $status): ?string
    {
        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }
        if (\is_string($status)) {
            return $status;
        }
        return null;
    }

    private function validateTransition(
        WorkflowInterface $stateMachine,
        string $from,
        string $to,
        string $entityLabel,
    ): void {
        if ($from === $to) {
            return;
        }

        foreach ($stateMachine->getDefinition()->getTransitions() as $transition) {
            if (\in_array($from, $transition->getFroms(), true)
                && \in_array($to, $transition->getTos(), true)) {
                return;
            }
        }

        throw new \LogicException(sprintf(
            'Transition de statut invalide pour %s : "%s" vers "%s" n\'est pas autorisee.',
            $entityLabel,
            $from,
            $to,
        ));
    }
}
