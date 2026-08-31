<?php

/*
===============================================================================
Entité : Product
===============================================================================
Objectif :
    Représenter un produit de skincare disponible à la vente.

Responsabilités :
    - Stocker les informations catalogue (nom, description, prix, image).
    - Gérer la disponibilité (isAvailable).
    - Définir les relations avec les Marques et les Problématiques de peau.

Propriétés principales :
    - id            : Identifiant unique.
    - name          : Nom du produit.
    - price         : Prix unitaire (decimal).
    - isAvailable   : Statut de disponibilité (booléen).
    - imageUrl      : Chemin vers l'image (nom du fichier stocké en BDD).
    - brand         : Marque du produit (Liaison).
    - skinConcerns  : Liste des problématiques ciblées (Acné, Sécheresse...).

Relations :
    - brand         : ManyToOne (Un produit appartient à une marque).
    - skinConcerns  : ManyToMany (Un produit traite plusieurs problématiques).
    - orderItems    : OneToMany (Un produit peut être dans plusieurs lignes de commande).

Note Technique :
    La table 'product' est liée aux tables 'brand' et 'skin_concern'.
    Les attributs #[Groups('product:read')] permettent au Serializer Symfony
    de sélectionner les champs à exposer dans l'API JSON.
    VichUploader gère l'upload via imageFile (non persistant) et imageUrl (persistant).
===============================================================================
*/

namespace App\Entity;

use Symfony\Component\HttpFoundation\File\File;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Vich\UploaderBundle\Mapping\Attribute as Vich;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'product')]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable] // <--- IMPORTANT : L'attribut doit être sur la classe
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups('product:read')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups('product:read')]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups('product:read')]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups('product:read')]
    private ?string $price = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups('product:read')]
    private bool $isAvailable = true;

    // Relation avec la marque
    #[ORM\ManyToOne(targetEntity: Brand::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups('product:read')]
    private ?Brand $brand = null;

    // Relation ManyToMany avec les problématiques
    #[ORM\ManyToMany(targetEntity: SkinConcern::class, inversedBy: 'products')]
    #[Groups('product:read')]
    private Collection $skinConcerns;

    #[ORM\Column(type: 'datetime')]
    #[Groups('product:read')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // ============================================================
    // GESTION DES IMAGES (VichUploader)
    // ============================================================

    // Ce champ NE sera PAS persisté en BDD.
    // Il sert uniquement au formulaire d'upload.
    #[Vich\UploadableField(
        mapping: 'produit_images', // <--- Assure-toi que ce nom correspond à ton config/packages/vich_uploader.yaml
        fileNameProperty: 'imageUrl',
        // size: 'image'  <-- SUPPRIMÉ : Ce paramètre n'existe pas et causait l'erreur
    )]
    #[Assert\Image(
        maxSize: '2M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'Veuillez uploader une image valide (JPEG, PNG, WebP).'
    )]
    private ?File $imageFile = null;

    // Ce champ SERA stocké en BDD (le nom du fichier : ex: "cerave-cream.webp")
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups('product:read')]
    private ?string $imageUrl = null;

    public function __construct()
    {
        $this->skinConcerns = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // --- Getters & Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }
    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }
    public function setIsAvailable(bool $isAvailable): self
    {
        $this->isAvailable = $isAvailable;
        return $this;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }
    public function setBrand(?Brand $brand): self
    {
        $this->brand = $brand;
        return $this;
    }

    /**
     * @return Collection<int, SkinConcern>
     */
    public function getSkinConcerns(): Collection
    {
        return $this->skinConcerns;
    }

    public function addSkinConcern(SkinConcern $skinConcern): self
    {
        if (!$this->skinConcerns->contains($skinConcern)) {
            $this->skinConcerns[] = $skinConcern;
        }
        return $this;
    }

    public function removeSkinConcern(SkinConcern $skinConcern): self
    {
        $this->skinConcerns->removeElement($skinConcern);
        return $this;
    }

    // --- Getters & Setters Image ---

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile = null): self
    {
        $this->imageFile = $imageFile;

        // On force la mise à jour de la date si l'image change (Lifecycle callbacks)
        if (null !== $imageFile) {
            $this->updatedAt = new \DateTime();
        }
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    // --- Dates ---

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}