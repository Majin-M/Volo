<?php

/*
===============================================================================
Contrôleur : UserCrudController (Admin)
===============================================================================
Objectif :
    Gerer le CRUD des utilisateurs (clients et administrateurs) dans le
    back-office EasyAdmin.

Responsabilites :
    - Afficher/editer les informations de profil et le role.
    - A la CREATION uniquement : hasher le mot de passe saisi avant
      persistance, et appliquer la meme politique de complexite que
      l'inscription publique (PasswordValidator).

Point de securite corrige :
    EasyAdmin lie un Field::new('password') directement a la propriete de
    l'entite : sans l'override de persistEntity() ci-dessous, la valeur
    saisie en clair dans le formulaire est enregistree telle quelle en base.
    persistEntity() est le point d'interception correct pour hasher le mot
    de passe avant l'ecriture reelle en base de donnees.

    Le formulaire d'edition (PAGE_EDIT) ne propose volontairement aucun champ
    mot de passe (voir configureFields ci-dessous) : cela evite tout risque
    d'ecraser accidentellement le hash existant, et updateEntity() n'a donc
    pas besoin d'etre surcharge.

Dependances :
    - UserPasswordHasherInterface : Pour hasher le mot de passe a la creation.
    - PasswordValidator : Pour appliquer la meme politique de complexite que
      AuthController::register (8 caracteres, chiffre, caractere special).
===============================================================================
*/

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\PasswordValidator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UserCrudController extends AbstractCrudController
{
    /**
     * @param UserPasswordHasherInterface $passwordHasher Pour hasher le mot de passe a la creation.
     * @param PasswordValidator $passwordValidator Pour appliquer la politique de complexite commune.
     */
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private PasswordValidator $passwordValidator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Utilisateurs')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvel Utilisateur')
            ->setPageTitle(Crud::PAGE_EDIT, 'Modifier l\'Utilisateur')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Détail de l\'Utilisateur');
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            IdField::new('id')->hideOnForm(),
            TextField::new('email')->setLabel('Email')->setHelp('L\'email est unique et utilisé pour se connecter'),
            TextField::new('firstName')->setLabel('Prénom'),
            TextField::new('lastName')->setLabel('Nom'),

            // allowMultipleChoices(true) est volontaire : un administrateur
            // reste aussi ROLE_USER (User::getRoles() l'ajoute automatiquement),
            // donc le formulaire doit pouvoir refleter un tableau de roles.
            ChoiceField::new('roles')
                ->setLabel('Rôle')
                ->setChoices([
                    'Client (Utilisateur)' => 'ROLE_USER',
                    'Administrateur' => 'ROLE_ADMIN',
                ])
                ->allowMultipleChoices(true)
                ->renderExpanded(false)
                ->setHelp('Choisissez le ou les rôles de l\'utilisateur.')
                ->setFormTypeOption('multiple', true),
        ];

        // -----------------------------------------------------------------------
        // Mot de passe : uniquement a la creation (voir persistEntity() plus bas
        // pour le hashage reel avant enregistrement en base).
        // -----------------------------------------------------------------------
        if ($pageName === Crud::PAGE_NEW) {
            $fields[] = Field::new('password')
                ->setLabel('Mot de passe initial')
                ->setFormType(PasswordType::class)
                ->setFormTypeOption('required', true)
                ->setFormTypeOption('constraints', [
                    new NotBlank(message: 'Le mot de passe est requis à la création.'),
                    new Callback([$this, 'validatePasswordComplexity']),
                ])
                ->onlyOnForms();
        }

        return $fields;
    }

    /**
     * Applique la meme politique de complexite que l'inscription publique
     * (AuthController::register), pour qu'un compte cree depuis le
     * back-office ne soit jamais moins bien protege qu'un compte client.
     *
     * @param string|null $value Mot de passe saisi dans le formulaire.
     * @param ExecutionContextInterface $context Contexte de validation Symfony.
     * @return void
     */
    public function validatePasswordComplexity(?string $value, ExecutionContextInterface $context): void
    {
        if (!$value) {
            return;
        }

        foreach ($this->passwordValidator->validate($value) as $error) {
            $context->buildViolation($error)->addViolation();
        }
    }

    /**
     * Hashe le mot de passe avant la premiere ecriture en base.
     * Sans cette surcharge, EasyAdmin persisterait la valeur saisie en clair.
     *
     * @param EntityManagerInterface $entityManager
     * @param mixed $entityInstance
     * @return void
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User && $entityInstance->getPassword()) {
            $entityInstance->setPassword(
                $this->passwordHasher->hashPassword($entityInstance, $entityInstance->getPassword())
            );
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
