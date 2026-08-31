<?php

/*
===============================================================================
Listener : WorkflowValidationListener
===============================================================================
Objectif :
    Empecher toute transition de statut invalide sur Order et Payment,
    quelle que soit l'origine de la modification (EasyAdmin, API, CLI).

Fonctionnement :
    - Ecoute l'evenement Doctrine preUpdate.
    - Si le champ 'status' a change, verifie que la transition est autorisee
      par la machine a etats definie dans config/packages/workflow.yaml.
    - Leve une LogicException si la transition est interdite, ce qui empeche
      le flush et affiche un message d'erreur dans EasyAdmin.

Dependances :
    - state_machine.order   : Machine a etats des commandes.
    - state_machine.payment : Machine a etats des paiements.
===============================================================================
*/

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
class WorkflowValidationListener
{
    public function __construct(
        #[Autowire(service: 'state_machine.order')] private WorkflowInterface $orderStateMachine,
        #[Autowire(service: 'state_machine.payment')] private WorkflowInterface $paymentStateMachine,
    ) {
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Order && $args->hasChangedField('status')) {
            $old = self::toEnum(OrderStatus::class, $args->getOldValue('status'));
            $new = self::toEnum(OrderStatus::class, $args->getNewValue('status'));
            $this->validateTransition($this->orderStateMachine, $old, $new, 'commande');
        }

        if ($entity instanceof Payment && $args->hasChangedField('status')) {
            $old = self::toEnum(PaymentStatus::class, $args->getOldValue('status'));
            $new = self::toEnum(PaymentStatus::class, $args->getNewValue('status'));
            $this->validateTransition($this->paymentStateMachine, $old, $new, 'paiement');
        }
    }

    /**
     * Doctrine peut retourner un string ou un BackedEnum selon le contexte.
     *
     * @template T of \BackedEnum
     * @param class-string<T> $enumClass
     * @return T
     */
    private static function toEnum(string $enumClass, mixed $value): \BackedEnum
    {
        if ($value instanceof $enumClass) {
            return $value;
        }

        \assert(\is_string($value));

        return $enumClass::from($value);
    }

    private function validateTransition(
        WorkflowInterface $stateMachine,
        \BackedEnum $oldStatus,
        \BackedEnum $newStatus,
        string $label,
    ): void {
        if ($oldStatus === $newStatus) {
            return;
        }

        $definition = $stateMachine->getDefinition();
        $valid = false;

        foreach ($definition->getTransitions() as $transition) {
            if (
                \in_array($oldStatus->value, $transition->getFroms(), true)
                && \in_array($newStatus->value, $transition->getTos(), true)
            ) {
                $valid = true;
                break;
            }
        }

        if ($valid) {
            return;
        }

        $reachable = [];
        foreach ($definition->getTransitions() as $transition) {
            if (\in_array($oldStatus->value, $transition->getFroms(), true)) {
                /** @var string $to */
                foreach ($transition->getTos() as $to) {
                    $reachable[$to] = true;
                }
            }
        }

        $reachableNames = array_keys($reachable);

        throw new \LogicException(sprintf(
            'Transition invalide pour %s : %s → %s. Etats accessibles depuis %s : %s.',
            $label,
            $oldStatus->value,
            $newStatus->value,
            $oldStatus->value,
            implode(', ', $reachableNames) ?: 'aucun (etat terminal)',
        ));
    }
}
