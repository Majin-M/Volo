<?php

/*
===============================================================================
Entité : Brand
===============================================================================
*/

namespace App\Entity;

use App\Repository\BrandRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\HttpFoundation\File\File; // <--- IMPORTANT
use Vich\UploaderBundle\Mapping\Attribute as Vich;
use Symfony\Component\Validator\Constraints as Assert; // <--- Pour la validation

#[ORM\Entity(repositoryClass: BrandRepository::class)]
#[ORM\Table(name: 'brand')]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable] // <--- IMPORTANT : L'attribut sur la classe
class Brand
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups('product:read')] 
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups('product:read')] 
    private ?string $name = null;

    // ============================================================
    // GESTION DES IMAGES (VichUploader)
    // ============================================================

    // Ce champ NE sera PAS persisté en BDD.
    // Il sert uniquement au formulaire d'upload.
    #[Vich\UploadableField(mapping: 'brand_logo', fileNameProperty: 'logoUrl')] // <--- mapping: 'brand_logo'
    #[Assert\Image(
        maxSize: '1M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'Veuillez uploader un logo valide (JPEG, PNG, WebP).'
    )]
    private ?File $imageFile = null; // <--- La propriété fichier manquante

    // Ce champ SERA stocké en BDD (le nom du fichier)
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups('product:read')]
    private ?string $logoUrl = null;

    // ... (Relation products, createdAt, updatedAt restent identiques)
    #[ORM\OneToMany(mappedBy: 'brand', targetEntity: Product::class)]
    private Collection $products;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->name ?? 'Marque sans nom';
    }

    // --- Getters & Setters Existants ---
    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getLogoUrl(): ?string { return $this->logoUrl; }
    public function setLogoUrl(?string $logoUrl): self { $this->logoUrl = $logoUrl; return $this; }

    // --- Getters & Setters Images (AJOUTER CES MÉTHODES) ---
    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile = null): self
    {
        $this->imageFile = $imageFile;
        if (null !== $imageFile) {
            $this->updatedAt = new \DateTime();
        }
        return $this;
    }

    // --- Autres méthodes (products, dates) ---
    public function getProducts(): Collection
    {
        return $this->products;
    }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
    
    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }
}