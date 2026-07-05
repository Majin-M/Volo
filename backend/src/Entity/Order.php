<?php

/*
===============================================================================
Entité : Order
===============================================================================
Objectif :
    Représenter une commande client passée sur la boutique.

Responsabilités :
    - Lier un utilisateur à un panier validé.
    - Stocker l'adresse de livraison.
    - Suivre le statut du paiement et de la livraison via l'Enum OrderStatus.
    - Calculer et stocker le montant total de la commande.
    - Contenir la liste des produits achetés (OrderItems).

Propriétés principales :
    - id              : Identifiant unique de la commande.
    - status          : Statut actuel (Enum OrderStatus).
    - total           : Montant total payé.
    - user            : Client ayant passé la commande.
    - items           : Liste des articles (Lignes de commande).
    - shippingAddress : Détails de l'adresse de livraison.

Note Technique :
    Le nom de la table en base de données est 'shop_order' car 'order'
    est un mot réservé en SQL.
===============================================================================
*/

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Enum\PaymentMethod;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups; 
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'shop_order')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups('order:read')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, enumType: OrderStatus::class)]
    #[Groups('order:read')]
    private OrderStatus $status = OrderStatus::PENDING;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups('order:read')]
    private ?string $total = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups('order:read')] 
    private ?User $user = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups('order:read')]
    private ?string $notes = null;

    // --- Champs d'adresse de livraison ---
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups('order:read')]
    private ?string $street = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups('order:read')]
    private ?string $city = null;

    #[ORM\Column(type: 'string', length: 20)]
    #[Groups('order:read')]
    private ?string $postalCode = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups('order:read')]
    private ?string $country = null;
    // -------------------------------------

    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'orderEntity', cascade: ['persist', 'remove'])]
    #[Groups('order:read')]
    private Collection $items;

    #[ORM\Column(type: 'datetime')]
    #[Groups('order:read')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    #[Groups('order:read')] // Ajout pour cohérence
    private \DateTimeInterface $updatedAt;

    // --- Champs Enums Paiement ---
    
    #[ORM\Column(type: 'string', length: 50, nullable: true)] // length=50 est souvent suffisant pour les Enums
    #[Groups('order:read')] // IMPORTANT pour l'API
    private ?PaymentStatus $paymentStatus = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Groups('order:read')] // IMPORTANT pour l'API
    private ?PaymentMethod $paymentMethod = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }
    
    // --- Getters & Setters ---

    public function getId(): ?int { return $this->id; }

    public function getStatus(): OrderStatus { return $this->status; }
    public function setStatus(OrderStatus $status): self { $this->status = $status; return $this; }

    public function getTotal(): ?string { return $this->total; }
    public function setTotal(string $total): self { $this->total = $total; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    // --- Getters & Setters Adresse ---
    public function getStreet(): ?string { return $this->street; }
    public function setStreet(string $street): self { $this->street = $street; return $this; }

    public function getCity(): ?string { return $this->city; }
    public function setCity(string $city): self { $this->city = $city; return $this; }

    public function getPostalCode(): ?string { return $this->postalCode; }
    public function setPostalCode(string $postalCode): self { $this->postalCode = $postalCode; return $this; }

    public function getCountry(): ?string { return $this->country; }
    public function setCountry(string $country): self { $this->country = $country; return $this; }

     public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    // --- Getters & Setters Enums Paiement ---
    
    public function getPaymentStatus(): ?PaymentStatus
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(?PaymentStatus $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PaymentMethod $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    // --- Items ---
    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items[] = $item;
            $item->setOrderEntity($this);
        }
        return $this;
    }

    public function removeItem(OrderItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getOrderEntity() === $this) {
                $item->setOrderEntity(null);
            }
        }
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}