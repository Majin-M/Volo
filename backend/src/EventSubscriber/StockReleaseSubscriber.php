<?php

/*
===============================================================================
Listener : StockReleaseSubscriber
===============================================================================
Objectif :
    Restituer au stock les unites retenues par une commande des que celle-ci
    passe a l'etat 'cancelled', QUELLE QUE SOIT L'ORIGINE de l'annulation.

Le trou qu'il bouche :
    Le stock est retire a la creation de la commande — c'est une reservation.
    Sa contrepartie, OrderService::releaseStock(), n'etait appelee que depuis
    deux endroits : la commande de balayage et le webhook Stripe. Une
    annulation faite a la main dans EasyAdmin n'en declenchait aucun : le
    back-office annulait la commande et immobilisait le stock definitivement.
    Le dispositif de restitution existait, et le chemin le plus evident pour
    un administrateur passait a cote.

Pourquoi ici, et pas dans OrderCrudController :
    Meme raison que StatusTransitionSubscriber, qui garde les transitions au
    meme niveau : Doctrine est le seul point de passage commun a toutes les
    ecritures. Une restitution posee dans le controleur EasyAdmin laisserait
    passer l'API, la console, et le prochain point d'entree qu'on ajoutera.

Pourquoi onFlush et pas preUpdate :
    On modifie ici une AUTRE entite que celle qui change (Product, pas Order).
    Depuis preUpdate, Doctrine a deja calcule ses changesets : la modification
    du produit serait appliquee en memoire puis jamais ecrite, sans la moindre
    erreur. C'est exactement le defaut qui avait rendu AuditSubscriber muet
    pendant des mois. onFlush est le dernier moment ou l'on peut encore
    inscrire une entite dans le flush en cours, a condition de calculer son
    changeset soi-meme.

Idempotence :
    La garantie ne vient pas d'ici mais de la machine a etats
    (StatusTransitionSubscriber) : 'cancelled' est un etat terminal, on n'y
    entre qu'une fois. Une commande deja annulee ne peut pas etre re-annulee,
    donc ce listener ne peut pas restituer deux fois.
===============================================================================
*/

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Entity\Product;
use App\Enum\OrderStatus;
use App\Service\OrderService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsDoctrineListener(event: Events::onFlush)]
class StockReleaseSubscriber
{
    public function __construct(
        private OrderService $orderService,
        private LoggerInterface $logger,
    ) {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $em = $event->getObjectManager();
        $uow = $em->getUnitOfWork();
        $productMetadata = $em->getClassMetadata(Product::class);

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Order) {
                continue;
            }

            $changeSet = $uow->getEntityChangeSet($entity);
            if (!isset($changeSet['status']) || !\is_array($changeSet['status'])) {
                continue;
            }

            $avant = $this->valeurStatut($changeSet['status'][0]);
            $apres = $this->valeurStatut($changeSet['status'][1]);

            if ($apres !== OrderStatus::CANCELLED->value || $avant === OrderStatus::CANCELLED->value) {
                continue;
            }

            $restituees = $this->orderService->releaseStock($entity);

            // Sans ce recalcul, les produits modifies ci-dessus ne sont pas
            // ecrits : ils ne figuraient pas dans le flush au moment ou
            // Doctrine a fige ses changesets.
            foreach ($entity->getItems() as $item) {
                $product = $item->getProduct();
                if ($product !== null) {
                    $uow->computeChangeSet($productMetadata, $product);
                }
            }

            $this->logger->info('Stock restitue apres annulation de commande.', [
                'order_id' => $entity->getId(),
                'statut_precedent' => $avant,
                'unites_restituees' => $restituees,
            ]);
        }
    }

    private function valeurStatut(mixed $statut): ?string
    {
        if ($statut instanceof \BackedEnum) {
            return (string) $statut->value;
        }
        if (\is_string($statut)) {
            return $statut;
        }

        return null;
    }
}
