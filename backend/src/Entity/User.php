<?php

/*
===============================================================================
Entité : User
===============================================================================
Objectif :
    Représenter un utilisateur du système (Client ou Administrateur).

Responsabilités :
    - Stocker les informations d'authentification (email, mot de passe hashé).
    - Stocker le profil public (prénom, nom).
    - Gérer les rôles de sécurité via l'interface Symfony UserInterface.
    - Gérer les horodatages de création et de mise à jour automatiquement.

Propriétés principales :
    - id            : Identifiant unique technique.
    - email         : Identifiant de connexion unique.
    - password      : Mot de passe hashé (jamais en clair).
    - roles         : Liste des rôles (tableau JSON).
    - firstName     : Prénom de l'utilisateur.
    - lastName      : Nom de l'utilisateur.
    - createdAt     : Date de création du compte.
    - updatedAt     : Date de dernière modification.

Relations :
    - orders        : OneToMany (L'utilisateur possède plusieurs commandes).
                      Cote inverse de Order::$user (ManyToOne, inversedBy: 'orders').

Dépendances :
    - Symfony\Component\Security\Core\User\UserInterface
    - Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface
===============================================================================
*/

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private ?string $password = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $lastName = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Order::class)]
    private Collection $orders;

    /*
    ===============================================================================
    Constructeur : Initialise les dates par défaut et les collections
    ===============================================================================
    */
    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->orders = new ArrayCollection();
        // Par défaut, tout le monde a au moins le rôle USER
        $this->roles = [UserRole::USER->value];
    }

    public function __toString(): string
    {
        return $this->firstName;
    }

    // --- Getters & Setters ---

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    /**
     * Méthode requise par l'interface UserInterface.
     * Retourne l'identifiant unique de l'utilisateur (l'email ici).
     */
    public function getUserIdentifier(): string { return (string) $this->email; }

    /**
     * Méthode requise par l'interface UserInterface.
     * Retourne la liste des rôles (garantit au moins ROLE_USER).
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    /**
     * Méthode requise par PasswordAuthenticatedUserInterface.
     */
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $firstName): self { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(string $lastName): self { $this->lastName = $lastName; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): self
    {
        if (!$this->orders->contains($order)) {
            $this->orders[] = $order;
            $order->setUser($this);
        }
        return $this;
    }

    public function removeOrder(Order $order): self
    {
        $this->orders->removeElement($order);
        return $this;
    }

    /*
    ===============================================================================
    Cycle de vie : Mise à jour automatique de la date
    ===============================================================================
    */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function eraseCredentials(): void {}
}