<?php

/*
===============================================================================
Repository : UserRepository
===============================================================================
Objectif :
    Centraliser toutes les requêtes liées à l'entité User (Utilisateur).

Responsabilités :
    - Récupérer un utilisateur via son email (pour l'authentification).
    - Récupérer le profil complet d'un utilisateur.

Exemple d'utilisation :
    $user = $userRepository->findOneByEmail('client@volo.fr');
===============================================================================
*/

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Utilisé pour mettre à jour (re-hasher) le mot de passe de l'utilisateur
     * automatiquement lorsque Symfony détecte qu'il faut améliorer la sécurité.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
    
    // Note : La méthode magique findOneByEmail() fonctionne grâce à la propriété $email
    // de l'entité, inutile de la réécrire manuellement sauf pour y ajouter de la logique spécifique.
}