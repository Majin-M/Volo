<?php

namespace App\EventSubscriber;

use App\Entity\AuditLog;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class AuditSubscriber
{
    private const TRACKED_FIELDS = [
        Order::class => ['status'],
        Payment::class => ['status'],
        User::class => ['password', 'roles'],
    ];

    public function __construct(private Security $security) {}

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

        $log = new AuditLog(
            entityType: $this->shortName($class),
            entityId: $entityId,
            action: 'create',
            userIdentifier: $this->currentUser(),
        );

        $event->getObjectManager()->persist($log);
        $event->getObjectManager()->flush();
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $entity = $event->getObject();
        $class = $entity::class;

        if (!isset(self::TRACKED_FIELDS[$class])) {
            return;
        }

        $em = $event->getObjectManager();
        $entityId = $this->getEntityId($entity);
        if ($entityId === null) {
            return;
        }

        foreach (self::TRACKED_FIELDS[$class] as $field) {
            if (!$event->hasChangedField($field)) {
                continue;
            }

            $old = $event->getOldValue($field);
            $new = $event->getNewValue($field);

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
        }
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
