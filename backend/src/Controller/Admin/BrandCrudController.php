<?php

/*
===============================================================================
Controleur : BrandCrudController (Admin CRUD)
===============================================================================
Objectif :
    Gerer les operations CRUD sur les marques depuis le panneau
    d'administration EasyAdmin.

Responsabilites :
    - Lister les marques avec leur nom et leur logo.
    - Permettre la creation et l'edition d'une marque.
    - Gerer l'upload du logo via VichUploaderBundle
      (champ imageFile en formulaire, logoUrl en lecture).

Dependances :
    - EasyAdmin : AbstractCrudController, Field, ImageField.
    - VichUploaderBundle : VichImageType pour l'upload de fichier.
===============================================================================
*/

namespace App\Controller\Admin;

use App\Entity\Brand;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use Vich\UploaderBundle\Form\Type\VichImageType;

class BrandCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Brand::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            Field::new('name')->setLabel('Nom de la marque'),
            
            // Image de la marque (Logo)
            ImageField::new('logoUrl')
                ->setLabel('Logo')
                ->setBasePath('/images/brands')
                ->onlyOnIndex(),
                
            Field::new('imageFile')
                ->setLabel('Logo (Fichier)')
                ->setFormType(VichImageType::class)
                ->onlyOnForms()
                ->setRequired(false),
        ];
    }
}