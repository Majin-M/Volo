<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Enum\PaymentMethod;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;

final class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    /**
     * Configuration globale du CRUD (titres, tris, pagination, etc.)
     */
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des Commandes')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvelle Commande')
            ->setPageTitle(Crud::PAGE_EDIT, 'Modifier la Commande')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Détail de la Commande')
            ->setSearchFields(['id', 'total', 'user.email', 'status', 'paymentStatus'])
            ->setDefaultSort(['createdAt' => 'DESC', 'total' => 'DESC'])
            ->setPaginatorPageSize(15)
            // Désactive la traduction automatique des champs si non désirée, 
            // mais notre méthode configureFields gère déjà le problème des Enums.
        ;
    }

    /**
     * Configuration des actions (boutons)
     */
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->addCssClass('btn btn-danger');
            })
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN, function (Action $action) {
                return $action->addCssClass('btn btn-primary');
            })
            ->add(Crud::PAGE_EDIT, Action::SAVE_AND_ADD_ANOTHER)
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_ADD_ANOTHER, function (Action $action) {
                return $action->addCssClass('btn btn-success');
            });
    }

    /**
     * Configuration des champs selon la page demandée.
     * C'est ici que nous résolvons le problème des Enums :
     * - En PAGE_NEW/EDIT : On utilise ChoiceField pour permettre la sélection.
     * - En PAGE_INDEX/DETAIL : On utilise TextField pour forcer l'affichage en chaîne et éviter l'erreur de traduction Twig.
     */
    public function configureFields(string $pageName): iterable
    {
        // -----------------------------------------------------------------------
        // SECTION 1 : Formulaire (Création et Modification)
        // -----------------------------------------------------------------------
        // Ici, nous avons besoin de listes déroulantes (ChoiceField) pour que l'admin
        // puisse sélectionner un statut, un moyen de paiement, etc.
        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            return [
                // Identifiant (seulement en lecture ou caché, selon besoin)
                IdField::new('id', 'ID')->hideOnForm(),

                // Client (Association)
                AssociationField::new('user', 'Client')
                    ->setRequired(true)
                    ->setFormTypeOption('class', User::class),

                // Statut de la commande (Enum: OrderStatus)
                ChoiceField::new('status', 'Statut')
                    ->setChoices(OrderStatus::cases())
                    // Force l'utilisation de EnumType avec la classe explicite
                    ->setFormTypeOption('class', OrderStatus::class)
                    ->setRequired(true)
                    ->renderAsBadges([ // Optionnel : pour l'affichage visuel
                        'pending' => 'warning',
                        'paid' => 'success',
                        'shipped' => 'info',
                        'cancelled' => 'danger',
                        'refunded' => 'secondary',
                    ]),

                // ✅ CORRECTION STATUT PAIEMENT
                ChoiceField::new('paymentStatus', 'Statut Paiement')
                    ->setChoices(PaymentStatus::cases())
                    ->setFormTypeOption('class', PaymentStatus::class) // <--- L'astuce magique
                    ->renderAsBadges([
                        'pending' => 'warning',
                        'paid' => 'success',
                        'failed' => 'danger',
                    ]),

                // ✅ CORRECTION MOYEN DE PAIEMENT
                ChoiceField::new('paymentMethod', 'Moyen de Paiement')
                    ->setChoices(PaymentMethod::cases())
                    ->setFormTypeOption('class', PaymentMethod::class) // <--- L'astuce magique
                    ->renderAsBadges([
                        'card' => 'primary',
                        'paypal' => 'info',
                        'bank_transfer' => 'secondary',
                    ]),

                // Montant total
                MoneyField::new('total', 'Montant Total')
                    ->setCurrency('EUR')
                    ->setStoredAsCents(false) // Si votre DB stocke en décimal, sinon false
                    ->setRequired(true),

                // Date de création (souvent auto-générée, mais visible)
                DateTimeField::new('createdAt', 'Date de Commande')
                    ->setFormat('dd/MM/yyyy HH:mm')
                    ->hideOnForm(), // On cache souvent la date de création sur le formulaire si elle est auto

                // Date de mise à jour
                DateTimeField::new('updatedAt', 'Dernière Modification')
                    ->setFormat('dd/MM/yyyy HH:mm')
                    ->hideOnForm(),

                // Ajoutez ici d'autres champs spécifiques à votre entité (adresse, notes, etc.)
                TextField::new('notes', 'Notes internes')
                    ->setRequired(false)
                    ->hideOnIndex(), // Optionnel : cacher dans la liste
            ];
        }

        // -----------------------------------------------------------------------
        // SECTION 2 : Liste (Index) et Détail (Show)
        // -----------------------------------------------------------------------
        // Ici, on utilise TextField pour éviter que le template 'choice.html.twig'
        // ne tente de traduire l'objet Enum, ce qui causait l'erreur.
        // Le formatValue retourne explicitement une chaîne de caractères.
        return [
            // Identifiant
            IdField::new('id', 'ID')
                ->formatValue(fn($value) => (string) $value),

            // Client
            AssociationField::new('user', 'Client')
                ->formatValue(fn($value) => $value ? $value->getEmail() : 'Inconnu'),

            // Statut de la commande (TRÈS IMPORTANT : Formatage explicite)
            ChoiceField::new('status', 'Statut')
                ->setChoices(OrderStatus::cases())
                ->formatValue(function ($value) {
                    if (!$value) return 'Non défini';
                    // ChoiceField gère l'objet Enum nativement, mais on force l'affichage du 'value'
                    return $value instanceof \UnitEnum ? $value->value : (string) $value;
                })
                // Optionnel : Affichage sous forme de badge coloré
                ->renderAsBadges([
                    'pending' => 'warning',
                    'paid' => 'success',
                    'shipped' => 'info',
                    'cancelled' => 'danger',
                    'refunded' => 'secondary',
                ]),

            // ✅ CORRECTION ENUM PAYMENT STATUS
            ChoiceField::new('paymentStatus', 'Statut Paiement')
                ->setChoices(PaymentStatus::cases())
                ->formatValue(function ($value) {
                    if (!$value) return 'Non défini';
                    return $value instanceof \UnitEnum ? $value->value : (string) $value;
                })
                ->renderAsBadges([
                    'pending' => 'warning',
                    'paid' => 'success',
                    'failed' => 'danger',
                ]),

            // ✅ CORRECTION ENUM PAYMENT METHOD
            ChoiceField::new('paymentMethod', 'Moyen de Paiement')
                ->setChoices(PaymentMethod::cases())
                ->formatValue(function ($value) {
                    if (!$value) return 'Non défini';
                    return $value instanceof \UnitEnum ? $value->value : (string) $value;
                }),
            // Montant Total (Formatage monétaire pour l'affichage)
            MoneyField::new('total', 'Montant Total')
                ->setCurrency('EUR')
                ->setStoredAsCents(false),


            // Dates
            DateTimeField::new('createdAt', 'Date')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->hideOnDetail(), // Optionnel : cacher sur la page détail si redondant

            DateTimeField::new('updatedAt', 'Mis à jour le')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->hideOnIndex(),

            // Notes (si nécessaire dans la liste)
            TextField::new('notes', 'Notes')
                ->hideOnIndex() // Souvent trop long pour la liste
                ->hideOnDetail(), // Ou le montrait ici si utile
        ];
    }
}
