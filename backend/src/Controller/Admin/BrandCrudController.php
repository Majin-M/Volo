<?php

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
                ->setBasePath('/media/brands')
                ->onlyOnIndex(),
                
            Field::new('imageFile')
                ->setLabel('Logo (Fichier)')
                ->setFormType(VichImageType::class)
                ->onlyOnForms()
                ->setRequired(false),
        ];
    }
}