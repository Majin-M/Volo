<?php

/*
===============================================================================
Listener : AuditSubscriber
===============================================================================
Objectif :
    Tracer dans audit_log les changements de statut (Order, Payment) et les
    modifications sensibles (User.password, User.roles).

Pourquoi deux mecanismes differents :
    Creations et modifications se heurtent a deux contraintes opposees, et
    une seule solution ne couvre pas les deux.

    - MODIFICATIONS -> onFlush. C'est le seul moment ou l'on peut encore
      ajouter des entites au flush en cours. Persister depuis preUpdate ne
      fonctionne PAS : Doctrine a deja calcule ses changesets, l'AuditLog
      est persiste mais jamais insere. C'etait le defaut de la version
      precedente — aucune modification n'a jamais ete tracee, seulement des
      creations. Ajouter une entite a ce stade impose d'appeler soi-meme
      computeChangeSet(), sans quoi elle est ignoree elle aussi.

    - CREATIONS -> postPersist puis postFlush. L'identifiant d'une entite
      nouvelle n'existe qu'apres son INSERT, donc apres onFlush. On note
      donc les creations au vol dans postPersist, et on les ecrit dans
      postFlush, hors du flush d'origine. La version precedente appelait
      flush() depuis postPersist, c'est-a-dire un flush imbrique pendant le
      commit : fragile, et sans garde contre la reentrance.

Garde contre la reentrance :
    Le flush de postFlush declenche a son tour onFlush/postFlush. La file
    est videe AVANT ce flush, et AuditLog n'est pas une classe suivie :
    le second passage ne produit rien et s'arrete immediatement.
===============================================================================
*/

namespace App\EventSubscriber;

use App\Entity\AuditLog;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postFlush)]
class AuditSubscriber
{
    private const TRACKED_FIELDS = [
        Order::class => ['status'],
        Payment::class => ['status'],
        User::class => ['password', 'roles'],
    ];

    /**
     * Creations reperees pendant le flush, en attente d'ecriture.
     *
     * @var AuditLog[]
     */
    private array $creationsEnAttente = [];

    public function __construct(private Security $security)
    {
    }

    /**
     * Trace les MODIFICATIONS, en les greffant sur le flush en cours.
     */
    public function onFlush(OnFlushEventArgs $event): void
    {
        $em = $event->getObjectManager();
        $uow = $em->getUnitOfWork();
        $metadata = $em->getClassMetadata(AuditLog::class);

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $class = $entity::class;

            if (!isset(self::TRACKED_FIELDS[$class])) {
                continue;
            }

            $entityId = $this->getEntityId($entity);
            if ($entityId === null) {
                continue;
            }

            $changeSet = $uow->getEntityChangeSet($entity);

            foreach (self::TRACKED_FIELDS[$class] as $field) {
                if (!isset($changeSet[$field])) {
                    continue;
                }

                // Doctrine rend un couple [ancien, nouveau] pour une colonne,
                // mais une PersistentCollection pour une association. Les
                // champs suivis sont tous scalaires ; on verifie quand meme
                // plutot que de deconstruire a l'aveugle.
                $changement = $changeSet[$field];
                if (!\is_array($changement)) {
                    continue;
                }

                $old = $changement[0];
                $new = $changement[1];

                $log = new AuditLog(
                    entityType: $this->shortName($class),
                    entityId: $entityId,
                    action: 'update',
                    field: $field,
                    oldValue: $this->stringify($old),
                    newValue: $this->stringify($new),
                    userIdentifier: $this->currentUser(),
                );

                $em->persist($log);

                // Indispensable : une entite persistee pendant onFlush n'est
                // pas inseree si son changeset n'est pas calcule a la main.
                $uow->computeChangeSet($metadata, $log);
            }
        }
    }

    /**
     * Repere les CREATIONS, dont l'identifiant vient d'etre attribue.
     */
    public function postPersist(PostPersistEventArgs $event): void
    {
        $entity = $event->getObject();
        $class = $entity::class;

        if (!isset(self::TRACKED_FIELDS[$class])) {
            return;
        }

        $entityId = $this->getEntityId($entity);
        if ($entityId === null) {
            return;
        }

        $this->creationsEnAttente[] = new AuditLog(
            entityType: $this->shortName($class),
            entityId: $entityId,
            action: 'create',
            userIdentifier: $this->currentUser(),
        );
    }

    /**
     * Ecrit les creations reperees, hors du flush d'origine.
     */
    public function postFlush(PostFlushEventArgs $event): void
    {
        if ($this->creationsEnAttente === []) {
            return;
        }

        // Vider AVANT de flusher : ce flush repasse par postFlush, et une
        // file encore pleine relancerait l'ecriture en boucle.
        $logs = $this->creationsEnAttente;
        $this->creationsEnAttente = [];

        $em = $event->getObjectManager();

        foreach ($logs as $log) {
            $em->persist($log);
        }

        $em->flush();
    }

    private function getEntityId(object $entity): ?int
    {
        if ($entity instanceof Order) {
            return $entity->getId();
        }
        if ($entity instanceof Payment) {
            return $entity->getId();
        }
        if ($entity instanceof User) {
            return $entity->getId();
        }
        return null;
    }

    private function currentUser(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        if (\is_array($value)) {
            return implode(',', array_filter(array_map(static function (mixed $v): ?string {
                if (\is_string($v)) {
                    return $v;
                }
                if (\is_int($v) || \is_float($v)) {
                    return (string) $v;
                }
                return null;
            }, $value), static fn(?string $v): bool => $v !== null));
        }
        if (\is_string($value)) {
            if (str_starts_with($value, '$2y$')) {
                return '[hashed]';
            }
            return $value;
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        return null;
    }
}
