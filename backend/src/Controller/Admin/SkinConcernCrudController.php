<?php

namespace App\Controller\Admin;

use App\Entity\SkinConcern;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class SkinConcernCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SkinConcern::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            Field::new('name')->setLabel('Nom affiché (ex: Acné)'),
            
            // Slug : généré automatiquement ou manuel
            SlugField::new('slug')->setTargetFieldName('name')->hideOnIndex(),
            
            TextareaField::new('description')
                ->setLabel('Description / Conseils')
                ->hideOnIndex(),
        ];
    }
}