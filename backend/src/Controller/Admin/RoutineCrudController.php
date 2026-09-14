<?php

/*
===============================================================================
Controleur : RoutineCrudController (Admin CRUD)
===============================================================================
Objectif :
    Gerer les routines de soin depuis le back-office.

Contexte :
    L'entite Routine existait sans aucune interface de gestion : la table ne
    pouvait etre remplie que par les fixtures. Ce controleur est ce qui rend
    le domaine reellement exploitable par un administrateur.

Point d'attention :
    'by_reference' => false sur la relation ManyToMany vers Product. Sans
    cela, EasyAdmin modifie la collection sans passer par addProduct() /
    removeProduct(), et les changements ne sont pas persistes — meme piege
    que pour les problematiques dans ProductCrudController.
===============================================================================
*/

namespace App\Controller\Admin;

use App\Entity\Routine;
use App\Enum\RoutineLevel;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Routine>
 */
class RoutineCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Routine::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Routine')
            ->setEntityLabelInPlural('Routines')
            ->setPageTitle(Crud::PAGE_INDEX, 'Routines de soin')
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name')->setLabel('Nom'),

            ChoiceField::new('level')
                ->setLabel('Niveau')
                ->setChoices(RoutineLevel::cases())
                ->renderAsBadges([
                    RoutineLevel::BEGINNER->value => 'success',
                    RoutineLevel::INTERMEDIATE->value => 'warning',
                    RoutineLevel::ADVANCED->value => 'danger',
                ]),

            TextareaField::new('description')
                ->setLabel('Description')
                ->hideOnIndex(),

            AssociationField::new('products')
                ->setLabel('Produits de la routine')
                ->setFormTypeOptions([
                    'by_reference' => false,
                ]),
        ];
    }
}
