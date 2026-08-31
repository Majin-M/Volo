<?php

/*
===============================================================================
Entité : Order
===============================================================================
Objectif :
    Représenter une commande client passée sur la boutique.

Responsabilités :
    - Lier un utilisateur à un panier validé.
    - Stocker l'adresse de livraison (copiée, pas référencée).
    - Suivre le statut de traitement de la commande via l'Enum OrderStatus.
    - Stocker le montant total de la commande.
    - Contenir la liste des produits achetés (OrderItems).

Propriétés principales :
    - id              : Identifiant unique de la commande.
    - status          : Statut de traitement (Enum OrderStatus).
    - total           : Montant total.
    - user            : Client ayant passé la commande.
    - items           : Liste des articles (Lignes de commande).
    - shippingAddress : Détails de l'adresse de livraison.
    - payment         : Le paiement associé (0 ou 1).

Note Technique :
    Le nom de la table en base de données est 'shop_order' car 'order'
    est un mot réservé en SQL.

Note Technique — LE STATUT DE PAIEMENT N'EST PLUS STOCKÉ ICI :
    Cette entité portait auparavant deux colonnes payment_status et
    payment_method, alors que l'entité Payment — déjà liée en OneToOne —
    portait exactement la même information. Deux colonnes pour une seule
    réalité, sans aucun mécanisme garantissant leur cohérence.

    Le constat qui a tranché : PaymentService n'écrivait QUE sur Payment.
    Les colonnes de Order n'étaient alimentées que par EasyAdmin, à la main.
    Elles étaient donc déjà fausses dès qu'un paiement passait par l'API.

    Désormais : Payment fait autorité, Order dérive.

    getPaymentStatus() et getPaymentMethod() sont conservés en lecture seule
    et restent exposés au groupe de sérialisation 'order:read' : le JSON
    renvoyé par l'API est INCHANGÉ (clés paymentStatus / paymentMethod
    toujours présentes). Aucune adaptation du front n'est nécessaire.
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

    /**
     * Côté inverse de la relation : la colonne order_id vit dans `payment`.
     *
     * Volontairement PAS exposé au groupe 'order:read' : sérialiser l'objet
     * Payment complet créerait une référence circulaire (Order → Payment →
     * Order) et exposerait clientSecret dans la réponse d'API. Seules les
     * deux valeurs utiles sont exposées, via les getters dérivés plus bas.
     */
    #[ORM\OneToOne(targetEntity: Payment::class, mappedBy: 'orderEntity')]
    private ?Payment $payment = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups('order:read')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    #[Groups('order:read')]
    private \DateTimeInterface $updatedAt;

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

    // --- Paiement ---

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    /**
     * Appelé par Payment::setOrderEntity() pour maintenir le côté inverse.
     * Ne pose pas la clé étrangère : celle-ci vit côté Payment (propriétaire).
     */
    public function setPayment(?Payment $payment): self
    {
        $this->payment = $payment;
        return $this;
    }

    /**
     * Statut de paiement, DÉRIVÉ de l'entité Payment — lecture seule.
     *
     * Retourne null tant qu'aucun paiement n'a été initié : c'est exactement
     * le comportement de l'ancienne colonne nullable, donc le contrat d'API
     * est préservé.
     */
    #[Groups('order:read')]
    public function getPaymentStatus(): ?PaymentStatus
    {
        return $this->payment?->getStatus();
    }

    /**
     * Moyen de paiement, DÉRIVÉ de l'entité Payment — lecture seule.
     */
    #[Groups('order:read')]
    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->payment?->getMethod();
    }

    /**
     * Raccourci de lecture, utile en Twig / EasyAdmin.
     */
    public function isPaid(): bool
    {
        return $this->payment?->getStatus() === PaymentStatus::CAPTURED;
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
