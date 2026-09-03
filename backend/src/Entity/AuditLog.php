<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_audit_entity')]
#[ORM\Index(columns: ['created_at'], name: 'idx_audit_date')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $entityType;

    #[ORM\Column(type: 'integer')]
    private int $entityId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $action;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $field = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $oldValue = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $newValue = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $userIdentifier = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct(
        string $entityType,
        int $entityId,
        string $action,
        ?string $field = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $userIdentifier = null,
    ) {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->action = $action;
        $this->field = $field;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
        $this->userIdentifier = $userIdentifier;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getEntityType(): string { return $this->entityType; }
    public function getEntityId(): int { return $this->entityId; }
    public function getAction(): string { return $this->action; }
    public function getField(): ?string { return $this->field; }
    public function getOldValue(): ?string { return $this->oldValue; }
    public function getNewValue(): ?string { return $this->newValue; }
    public function getUserIdentifier(): ?string { return $this->userIdentifier; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
