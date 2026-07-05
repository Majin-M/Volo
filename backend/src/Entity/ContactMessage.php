<?php

/*
===============================================================================
Entité : ContactMessage
===============================================================================
Objectif :
    Stocker les messages envoyés via le formulaire de contact public.

Responsabilités :
    - Sauvegarder les coordonnées et le message du client.
    - Permettre aux administrateurs de marquer le message comme "traité".

Propriétés principales :
    - id          : Identifiant unique.
    - firstName   : Prénom de l'expéditeur.
    - email       : Email de réponse.
    - subject     : Sujet du message.
    - message     : Contenu du message.
    - isProcessed : Booléen indiquant si l'admin a répondu/traité le message.

Relations :
    - Aucune relation directe avec d'autres entités métier (isolée).

Note Technique :
    Utile pour le support client et le back-office.
===============================================================================
*/

namespace App\Entity;

use App\Repository\ContactMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactMessageRepository::class)]
#[ORM\Table(name: 'contact_message')]
#[ORM\HasLifecycleCallbacks]
class ContactMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text')]
    private ?string $message = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isProcessed = false;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // --- Getters & Setters ---

    public function getId(): ?int { return $this->id; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $firstName): self { $this->firstName = $firstName; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(string $subject): self { $this->subject = $subject; return $this; }

    public function getMessage(): ?string { return $this->message; }
    public function setMessage(string $message): self { $this->message = $message; return $this; }

    public function isProcessed(): bool { return $this->isProcessed; }
    public function setProcessed(bool $isProcessed): self { $this->isProcessed = $isProcessed; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTime(); }
}