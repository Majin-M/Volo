<?php

/*
===============================================================================
Entité : ContactMessage
===============================================================================
Objectif :
    Archiver les messages envoyés via le formulaire de contact public.

    ARCHIVE, pas outil de travail. Depuis le 17/07/2026, ContactService
    persiste le message ET notifie l'administrateur par email : la base
    garantit qu'un envoi raté ne perd rien, mais le traitement se fait dans la
    boite mail (etat lu/non-lu, reponses, archives), pas ici. Voir
    docs/MODELE_DONNEES.md 6.5.

Responsabilités :
    - Conserver une trace durable de chaque message recu.

Propriétés principales :
    - id          : Identifiant unique.
    - firstName   : Prénom de l'expéditeur.
    - email       : Email du visiteur (va dans le Reply-To de la notification).
    - subject     : Sujet du message.
    - message     : Contenu du message (assaini par strip_tags a l'entree).
    - isProcessed : VESTIGE. Plus lu par personne depuis le passage a la
                    notification email. A retirer lors d'un prochain nettoyage.

Relations :
    - Aucune. L'association TRAITE vers User (RG12) a ete abandonnee avec le
      passage a la notification email — cf. docs/MODELE_DONNEES.md 6.5.
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