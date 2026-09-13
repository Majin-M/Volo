<?php

/*
===============================================================================
Listener : StatusTransitionSubscriber
===============================================================================
Objectif :
    Empecher toute transition de statut invalide sur Order et Payment,
    quelle que soit l'origine de la modification (EasyAdmin, API, CLI).

Fonctionnement :
    - Ecoute l'evenement Doctrine preUpdate.
    - Si le champ 'status' a change, verifie que la transition est autorisee
      par la machine a etats definie dans config/packages/workflow.yaml.
    - Leve une LogicException si la transition est interdite, ce qui empeche
      le flush et affiche un message d'erreur dans EasyAdmin. Le message
      enumere les etats reellement atteignables, pour que l'administrateur
      sache quoi faire plutot que seulement ce qui est interdit.

Pourquoi ici plutot que dans les services :
    C'est le seul point de passage commun a toutes les ecritures. Une
    validation placee dans OrderService laisserait EasyAdmin la contourner.

Dependances :
    - state_machine.order   : Machine a etats des commandes.
    - state_machine.payment : Machine a etats des paiements.
===============================================================================
*/

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Entity\Payment;
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

    /**
     * Doctrine restitue tantot un BackedEnum, tantot la chaine brute selon le
     * contexte d'hydratation. Une valeur inattendue rend null : on laisse
     * alors passer plutot que d'echouer sur un cas qu'on ne sait pas juger.
     */
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

        $definition = $stateMachine->getDefinition();

        foreach ($definition->getTransitions() as $transition) {
            if (\in_array($from, $transition->getFroms(), true)
                && \in_array($to, $transition->getTos(), true)) {
                return;
            }
        }

        // getTos() n'est pas type par le composant Workflow : on ne retient
        // que les valeurs exploitables comme nom d'etat.
        $reachable = [];
        foreach ($definition->getTransitions() as $transition) {
            if (\in_array($from, $transition->getFroms(), true)) {
                foreach ($transition->getTos() as $target) {
                    if (\is_string($target)) {
                        $reachable[$target] = true;
                    }
                }
            }
        }

        throw new \LogicException(sprintf(
            'Transition de statut invalide pour %s : "%s" vers "%s" n\'est pas autorisee. Etats accessibles depuis "%s" : %s.',
            $entityLabel,
            $from,
            $to,
            $from,
            implode(', ', array_keys($reachable)) ?: 'aucun (etat terminal)',
        ));
    }
}
