<?php

/*
===============================================================================
OrderCrudController — back-office des commandes
===============================================================================
Note Technique — LES CHAMPS DE PAIEMENT SONT DEVENUS DES DÉRIVÉS :
    Order::$paymentStatus et Order::$paymentMethod ont été supprimés : ils
    dupliquaient Payment::$status et Payment::$method (cf. l'en-tête de
    Order.php et docs/MODELE_DONNEES.md section 6.1).

    Conséquence directe ici : ces deux champs n'ont plus de setter, ils ne
    peuvent donc plus apparaître dans le formulaire NEW/EDIT — EasyAdmin
    lèverait une exception en tentant d'écrire dessus. Ils restent affichés
    en INDEX et DETAIL, en lecture seule, via les getters dérivés.

    Pour MODIFIER un statut de paiement, l'administrateur passe désormais par
    PaymentCrudController (menu « Ventes › Paiements »), c'est-à-dire au bon
    endroit : là où la donnée vit réellement.

Note Technique — RECHERCHE :
    setSearchFields() référençait 'paymentStatus', qui n'est plus une colonne
    Doctrine. La recherche traverse maintenant l'association : 'payment.status'
    — même notation que 'user.email', déjà utilisée ici.
===============================================================================
*/

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class OrderCrudController extends AbstractCrudController
{
    /**
     * Couleurs de badge par statut de commande.
     * Les clés DOIVENT correspondre aux valeurs de OrderStatus — une clé
     * inexistante n'affiche simplement aucun badge, sans erreur : c'est
     * pourquoi 'refunded' (qui n'existe pas dans OrderStatus) traînait ici
     * sans que personne ne le remarque, tandis que 'delivered' manquait.
     */
    private const ORDER_STATUS_BADGES = [
        'pending'   => 'warning',
        'paid'      => 'success',
        'shipped'   => 'info',
        'delivered' => 'primary',
        'cancelled' => 'danger',
    ];

    /** Clés = valeurs de PaymentStatus (pending, captured, failed, refunded). */
    private const PAYMENT_STATUS_BADGES = [
        'pending'  => 'warning',
        'captured' => 'success',
        'failed'   => 'danger',
        'refunded' => 'secondary',
    ];

    /** Clés = valeurs de PaymentMethod (card, paypal). */
    private const PAYMENT_METHOD_BADGES = [
        'card'   => 'primary',
        'paypal' => 'info',
    ];

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des Commandes')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvelle Commande')
            ->setPageTitle(Crud::PAGE_EDIT, 'Modifier la Commande')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Détail de la Commande')
            // 'payment.status' traverse l'association — 'paymentStatus' n'est
            // plus une colonne et ferait échouer la requête de recherche.
            ->setSearchFields(['id', 'total', 'user.email', 'status', 'payment.status'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(15);
    }

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

    public function configureFields(string $pageName): iterable
    {
        // -------------------------------------------------------------------
        // SECTION 1 : Formulaire (Création et Modification)
        // -------------------------------------------------------------------
        // Les champs de paiement N'APPARAISSENT PLUS ICI : ce sont désormais
        // des dérivés en lecture seule de l'entité Payment. Les afficher
        // ferait planter EasyAdmin (aucun setter à appeler).
        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            return [
                IdField::new('id', 'ID')->hideOnForm(),

                AssociationField::new('user', 'Client')
                    ->setRequired(true)
                    ->setFormTypeOption('class', User::class),

                ChoiceField::new('status', 'Statut')
                    ->setChoices(OrderStatus::cases())
                    ->setFormTypeOption('class', OrderStatus::class)
                    ->setRequired(true)
                    ->renderAsBadges(self::ORDER_STATUS_BADGES),

                MoneyField::new('total', 'Montant Total')
                    ->setCurrency('EUR')
                    ->setStoredAsCents(false)
                    ->setRequired(true),

                DateTimeField::new('createdAt', 'Date de Commande')
                    ->setFormat('dd/MM/yyyy HH:mm')
                    ->hideOnForm(),

                DateTimeField::new('updatedAt', 'Dernière Modification')
                    ->setFormat('dd/MM/yyyy HH:mm')
                    ->hideOnForm(),

                TextField::new('notes', 'Notes internes')
                    ->setRequired(false)
                    ->hideOnIndex(),
            ];
        }

        // -------------------------------------------------------------------
        // SECTION 2 : Liste (Index) et Détail (Show)
        // -------------------------------------------------------------------
        // formatValue() force une chaîne : sans lui, le template de choix
        // tente de traduire l'objet Enum et lève une erreur Twig.
        return [
            IdField::new('id', 'ID')
                ->formatValue(fn ($value) => (string) $value),

            AssociationField::new('user', 'Client')
                ->formatValue(fn ($value) => $value ? $value->getEmail() : 'Inconnu'),

            ChoiceField::new('status', 'Statut')
                ->setChoices(OrderStatus::cases())
                ->formatValue(fn ($value) => $value instanceof \UnitEnum ? $value->value : 'Non défini')
                ->renderAsBadges(self::ORDER_STATUS_BADGES),

            // Dérivé de Payment — lecture seule. 'Non initié' plutôt que
            // 'Non défini' : une commande sans paiement n'est pas une donnée
            // manquante, c'est un panier validé dont le paiement n'a pas
            // encore commencé.
            ChoiceField::new('paymentStatus', 'Statut Paiement')
                ->setChoices(PaymentStatus::cases())
                ->formatValue(fn ($value) => $value instanceof \UnitEnum ? $value->value : 'Non initié')
                ->renderAsBadges(self::PAYMENT_STATUS_BADGES),

            // Dérivé de Payment — lecture seule.
            ChoiceField::new('paymentMethod', 'Moyen de Paiement')
                ->setChoices(PaymentMethod::cases())
                ->formatValue(fn ($value) => $value instanceof \UnitEnum ? $value->value : 'Non initié')
                ->renderAsBadges(self::PAYMENT_METHOD_BADGES),

            MoneyField::new('total', 'Montant Total')
                ->setCurrency('EUR')
                ->setStoredAsCents(false),

            DateTimeField::new('createdAt', 'Date')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->hideOnDetail(),

            DateTimeField::new('updatedAt', 'Mis à jour le')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->hideOnIndex(),

            TextField::new('notes', 'Notes')
                ->hideOnIndex(),
        ];
    }
}
