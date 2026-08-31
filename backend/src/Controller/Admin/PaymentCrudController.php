<?php

/*
===============================================================================
PaymentCrudController — back-office des paiements
===============================================================================
Objectif :
    Rendre les enregistrements de paiement consultables et modifiables depuis
    le back-office.

Pourquoi ce contrôleur existe :
    Order::$paymentStatus et Order::$paymentMethod ont été supprimés (doublon
    de Payment — cf. docs/MODELE_DONNEES.md section 6.1). L'administrateur
    pouvait auparavant modifier le statut de paiement depuis le formulaire de
    commande ; il le fait désormais ici, c'est-à-dire là où la donnée vit.

    Sans ce contrôleur, la correction du doublon aurait été une régression
    fonctionnelle : plus aucun moyen de marquer une commande payée à la main.
    Ce moyen reste nécessaire tant que le webhook Stripe n'existe pas
    (cf. docs/DIAGRAMME_ETATS.md section 2).

Note Technique — SUPPRESSION DÉSACTIVÉE :
    Action::DELETE est retirée. Un enregistrement financier est une trace de
    ce qui s'est réellement passé : il ne se supprime pas, il se complète.
    Un paiement échoué reste 'failed' et un nouvel essai crée un NOUVEAU
    Payment (cf. docs/DIAGRAMME_ETATS.md section 2).

Note Technique — clientSecret :
    Affiché en détail uniquement, jamais en liste, et non modifiable. Ce jeton
    est émis par Stripe : le saisir à la main n'a aucun sens.
    Sa présence même en base est discutable (docs/MODELE_DONNEES.md 6.3).
===============================================================================
*/

namespace App\Controller\Admin;

use App\Entity\Payment;
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

final class PaymentCrudController extends AbstractCrudController
{
    private const STATUS_BADGES = [
        'pending'  => 'warning',
        'captured' => 'success',
        'failed'   => 'danger',
        'refunded' => 'secondary',
    ];

    private const METHOD_BADGES = [
        'card'   => 'primary',
        'paypal' => 'info',
    ];

    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Paiements')
            ->setPageTitle(Crud::PAGE_EDIT, 'Modifier le paiement')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Détail du paiement')
            ->setSearchFields(['id', 'amount', 'orderEntity.id', 'orderEntity.user.email'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            // Un paiement naît d'un parcours d'achat, jamais d'une saisie
            // manuelle : sans intention Stripe correspondante, l'enregistrement
            // ne référencerait aucune transaction réelle.
            ->disable(Action::NEW)
            // Cf. note d'en-tête : une trace financière ne se supprime pas.
            ->disable(Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->formatValue(fn ($value) => (string) $value)
            ->hideOnForm();

        yield AssociationField::new('orderEntity', 'Commande')
            ->formatValue(fn ($value) => $value ? sprintf('#%d', $value->getId()) : '—')
            ->setDisabled();

        yield ChoiceField::new('status', 'Statut')
            ->setChoices(PaymentStatus::cases())
            ->setFormTypeOption('class', PaymentStatus::class)
            ->setRequired(true)
            ->formatValue(fn ($value) => $value instanceof \UnitEnum ? $value->value : 'Non défini')
            ->renderAsBadges(self::STATUS_BADGES)
            ->setHelp('Modification manuelle temporaire, en attendant le webhook Stripe.');

        yield ChoiceField::new('method', 'Moyen de paiement')
            ->setChoices(PaymentMethod::cases())
            ->setFormTypeOption('class', PaymentMethod::class)
            ->setRequired(true)
            ->formatValue(fn ($value) => $value instanceof \UnitEnum ? $value->value : 'Non défini')
            ->renderAsBadges(self::METHOD_BADGES);

        yield MoneyField::new('amount', 'Montant')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setDisabled();

        yield TextField::new('clientSecret', 'Client secret (Stripe)')
            ->onlyOnDetail()
            ->setDisabled();

        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();

        yield DateTimeField::new('updatedAt', 'Mis à jour le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm()
            ->hideOnIndex();
    }
}
