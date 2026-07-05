<?php

/*
===============================================================================
Entité : Payment
===============================================================================
Objectif :
    Enregistrer les informations de paiement d'une commande.

Responsabilités :
    - Lier une transaction financière à une commande.
    - Stocker le statut de la transaction (via Enum PaymentStatus).
    - Sauvegarder les tokens de sécurité (clientSecret) pour Stripe/PayPal.

Propriétés principales :
    - id            : Identifiant unique.
    - status        : Statut du paiement (Enum: pending, captured, failed).
    - method        : Moyen de paiement utilisé (Enum: card, paypal).
    - clientSecret  : Secret client fourni par la passerelle de paiement (Stripe).
    - amount        : Montant payé.

Relations :
    - orderEntity   : OneToOne (Un paiement est lié à une unique commande).

Note Technique :
    La relation OneToOne avec l'ordre permet de garantir l'intégrité
    transactionnelle.
===============================================================================
*/

namespace App\Entity;

use App\Enum\PaymentStatus;
use App\Enum\PaymentMethod;
use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payment')]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::PENDING;

    #[ORM\Column(type: 'string', length: 255, enumType: PaymentMethod::class)]
    private PaymentMethod $method;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $clientSecret = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $amount = null;

    #[ORM\OneToOne(targetEntity: Order::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $orderEntity = null;

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

    public function getStatus(): PaymentStatus { return $this->status; }
    public function setStatus(PaymentStatus $status): self { $this->status = $status; return $this; }

    public function getMethod(): PaymentMethod { return $this->method; }
    public function setMethod(PaymentMethod $method): self { $this->method = $method; return $this; }

    public function getClientSecret(): ?string { return $this->clientSecret; }
    public function setClientSecret(?string $clientSecret): self { $this->clientSecret = $clientSecret; return $this; }

    public function getAmount(): ?string { return $this->amount; }
    public function setAmount(string $amount): self { $this->amount = $amount; return $this; }

    public function getOrderEntity(): ?Order { return $this->orderEntity; }
    public function setOrderEntity(Order $orderEntity): self { $this->orderEntity = $orderEntity; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTime(); }
}