<?php

/*
===============================================================================
Entité : OrderItem
===============================================================================
Objectif :
    Représenter une ligne individuelle dans une commande (un produit avec une quantité).

Responsabilités :
    - Lier un produit à une commande avec une quantité spécifique.
    - "Geler" le prix unitaire au moment de l'achat (historisation).
    - Stocker le nom du produit (snapshot) au cas où le produit est supprimé ou renommé.

Propriétés principales :
    - id          : Identifiant unique de la ligne.
    - quantity    : Quantité commandée.
    - unitPrice   : Prix payé pour UN produit au moment de l'achat.
    - productName : Nom du produit au moment de l'achat.

Relations :
    - orderEntity : ManyToOne (Appartient à une commande 'shop_order').
    - product     : ManyToOne (Référence le produit concerné).

Note Technique :
    Le prix est stocké ici pour garantir que si le prix du produit change
    dans le catalogue, cela n'affecte pas les historiques de commande.
===============================================================================
*/

namespace App\Entity;

use Symfony\Component\Serializer\Annotation\Groups;
use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_item')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups('order:read')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    #[Groups('order:read')] 
    private ?int $quantity = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups('order:read')] 
    private ?string $unitPrice = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups('order:read')] 
    private ?string $productName = null;

    // On utilise 'orderEntity' car 'order' est un mot clé SQL réservé
    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $orderEntity = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]

     // NOTE: On n'ajoute PAS de groupe sur la relation $product pour éviter
    // de boucler infiniment (Order -> Item -> Product -> Brand -> Products...)
    private ?Product $product = null;

    // --- Getters & Setters ---

    public function getId(): ?int { return $this->id; }

    public function getQuantity(): ?int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = $quantity; return $this; }

    public function getUnitPrice(): ?string { return $this->unitPrice; }
    public function setUnitPrice(string $unitPrice): self { $this->unitPrice = $unitPrice; return $this; }

    public function getProductName(): ?string { return $this->productName; }
    public function setProductName(string $productName): self { $this->productName = $productName; return $this; }

    public function getOrderEntity(): ?Order { return $this->orderEntity; }
    public function setOrderEntity(?Order $orderEntity): self { $this->orderEntity = $orderEntity; return $this; }

    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): self { $this->product = $product; return $this; }
}