<?php

/*
===============================================================================
Controleur : ProductCrudController (Admin CRUD)
===============================================================================
Objectif :
    Gerer les operations CRUD sur les produits depuis le panneau
    d'administration EasyAdmin.

Responsabilites :
    - Lister les produits (nom, image, prix, marque, disponibilite).
    - Permettre la creation et l'edition d'un produit avec upload
      d'image via VichUploaderBundle.
    - Gerer les relations ManyToMany avec SkinConcern (by_reference
      false pour garantir la synchronisation bidirectionnelle).
    - Gerer la relation ManyToOne avec Brand.

Dependances :
    - EasyAdmin : AbstractCrudController et champs (TextField,
      NumberField, ImageField, AssociationField, TextareaField).
    - VichUploaderBundle : VichImageType pour l'upload d'image.
===============================================================================
*/

namespace App\Controller\Admin;

use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            // Dans la liste (Index)
            TextField::new('name')->setLabel('Nom'),
            ImageField::new('imageUrl')
                ->setLabel('Image')
                ->setBasePath('/images/products') 
                ->onlyOnIndex(),
            NumberField::new('price')->setLabel('Prix'),
            AssociationField::new('brand')->setLabel('Marque'),
            Field::new('isAvailable')->setLabel('Disponible ?'),
            
            // Dans le formulaire (Create/Edit)
            TextField::new('name'),
            TextareaField::new('description')->setLabel('Description'),
            
            // CHAMP UPLOAD IMAGE
            Field::new('imageFile')
                ->setLabel('Image (Fichier)')
                ->setFormType(VichImageType::class)
                ->onlyOnForms()
                ->setRequired(false),
                
            NumberField::new('price'),
            Field::new('isAvailable')->setLabel('Disponible'),
            AssociationField::new('brand'),
            
            // Relations ManyToMany (Problématiques)
            AssociationField::new('skinConcerns')->setLabel('Problématiques')->setFormTypeOptions([
                'by_reference' => false // Important pour ManyToMany dans EasyAdmin
            ]),
        ];
    }
}