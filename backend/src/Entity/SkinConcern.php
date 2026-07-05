<?php

/*
===============================================================================
Entité : SkinConcern
===============================================================================
Objectif :
    Représenter une problématique de peau (ex: Acné, Sécheresse, Rides).

Responsabilités :
    - Catégoriser les produits selon le besoin du client.
    - Fournir un slug unique pour les URLs de filtrage.

Propriétés principales :
    - id          : Identifiant unique.
    - name        : Nom affiché (ex: "Acné").
    - slug        : Identifiant unique technique (ex: "acne").
    - description : Texte explicatif pour l'utilisateur.

Relations :
    - products    : ManyToMany (Une problématique peut être traitée par plusieurs produits).

Note Technique :
    L'attribut Groups permet d'exposer id, name et slug dans l'API Produit.
    La propriété 'products' (relation inverse) n'est pas exposée pour éviter
    les boucles JSON (Circular Reference).
===============================================================================
*/

namespace App\Entity;

use App\Repository\SkinConcernRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SkinConcernRepository::class)]
#[ORM\Table(name: 'skin_concern')]
#[ORM\HasLifecycleCallbacks]
class SkinConcern
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups('product:read')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups('product:read')]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Groups('product:read')] 
    private ?string $slug = null;

    // La description n'est pas dans l'API spec listée pour skinConcerns, donc on peut l'omettre ou l'ajouter si besoin
    #[ORM\Column(type: 'text', nullable: true)]
    // Pas de groupe ici, car le JSON attendu ne contient que id, name, slug
    private ?string $description = null;

    // PAS de groupe ici pour éviter la référence circulaire (SkinConcern -> Products -> SkinConcern ...)
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'skinConcerns')]
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
        // Retourne le nom si il existe, sinon un texte par défaut
        return $this->name ?? 'Marque sans nom';
    }

    // --- Getters & Setters ---

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTime(); }
}