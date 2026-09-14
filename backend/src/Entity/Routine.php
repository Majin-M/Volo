<?php

/*
===============================================================================
Entité : Routine
===============================================================================
Objectif :
    Définir un ensemble de produits recommandés pour un soin spécifique.

Responsabilités :
    - Proposer un parcours de soin (ex: Routine Hydratation Débutant).
    - Classer les routines par niveau de difficulté (Enum RoutineLevel).
    - Lier des produits spécifiques à cette routine.

Propriétés principales :
    - id          : Identifiant unique.
    - name        : Nom de la routine.
    - level       : Niveau de complexité (Enum: beginner, intermediate, advanced).
    - description : Explication de la routine.

Relations :
    - products    : ManyToMany (Une routine contient plusieurs produits).
                    Note : Une table pivot 'routine_product' sera générée.

Note Technique :
    Permet de guider l'utilisateur dans le choix de ses produits
    selon son expertise en skincare.
===============================================================================
*/

namespace App\Entity;

use App\Enum\RoutineLevel;
use App\Repository\RoutineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: RoutineRepository::class)]
#[ORM\Table(name: 'routine')]
#[ORM\HasLifecycleCallbacks]
class Routine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups('routine:read')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups('routine:read')]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255, enumType: RoutineLevel::class)]
    #[Groups('routine:read')]
    private RoutineLevel $level;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups('routine:read')]
    private ?string $description = null;

    // Les produits portent le groupe 'product:read' : c'est lui qui decide de
    // ce qui est expose pour chaque produit imbrique dans une routine.
    #[ORM\ManyToMany(targetEntity: Product::class)]
    #[Groups('routine:read')]
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

    // --- Getters & Setters ---

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getLevel(): RoutineLevel { return $this->level; }
    public function setLevel(RoutineLevel $level): self { $this->level = $level; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products[] = $product;
        }
        return $this;
    }

    public function removeProduct(Product $product): self
    {
        $this->products->removeElement($product);
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTime(); }
}