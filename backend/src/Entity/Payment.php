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
    - status        : Statut du paiement (Enum: pending, captured, failed, refunded).
    - method        : Moyen de paiement utilisé (Enum: card, paypal).
    - clientSecret  : Secret client fourni par la passerelle de paiement (Stripe).
    - amount        : Montant payé.

Relations :
    - orderEntity   : OneToOne (Un paiement est lié à une unique commande).
                      Côté propriétaire : la colonne order_id vit dans cette table.

Note Technique — SOURCE DE VÉRITÉ DU PAIEMENT :
    Cette entité est la SEULE source de vérité pour le statut et le moyen de
    paiement. Order ne stocke plus ces informations : Order::getPaymentStatus()
    et Order::getPaymentMethod() les dérivent d'ici.

    Auparavant, Order portait ses propres colonnes payment_status /
    payment_method, dupliquant cette information sans aucun mécanisme de
    cohérence entre les deux. Le futur webhook Stripe aurait dû penser à
    mettre les deux à jour ; en oublier une aurait fait diverger l'API et le
    back-office en silence.

Note Technique — CASCADE :
    Cette relation ne porte VOLONTAIREMENT aucun cascade.

    Elle déclarait auparavant cascade: ['persist', 'remove'], ce qui signifiait
    « supprimer un paiement supprime la commande » — et, par cascade en chaîne
    depuis Order::$items, ses lignes de commande. Un administrateur supprimant
    une ligne de paiement dans EasyAdmin effaçait l'historique d'achat du client.

    Le cascade était à l'envers : un paiement est un détail d'une commande,
    jamais son propriétaire. Un enregistrement financier ne doit de toute façon
    jamais être supprimé (cf. docs/DIAGRAMME_ETATS.md section 2) : un échec
    crée un nouveau Payment, il n'écrase pas le précédent.
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

    /**
     * Côté propriétaire de la relation : la colonne order_id est dans `payment`.
     *
     * Aucun cascade (cf. note d'en-tête). inversedBy permet à Order d'exposer
     * Order::getPayment() et donc de dériver son statut de paiement.
     */
    #[ORM\OneToOne(targetEntity: Order::class, inversedBy: 'payment')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
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

    /**
     * Maintient le côté inverse à jour : sans cela, $order->getPayment()
     * retournerait null tant que l'EntityManager n'a pas été vidé/rechargé,
     * alors même que le Payment référence bien la commande.
     */
    public function setOrderEntity(Order $orderEntity): self
    {
        $this->orderEntity = $orderEntity;

        if ($orderEntity->getPayment() !== $this) {
            $orderEntity->setPayment($this);
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTime(); }
}
