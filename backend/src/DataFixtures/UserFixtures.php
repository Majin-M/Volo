<?php

/*
===============================================================================
Fixture : UserFixtures
===============================================================================
Objectif :
    Charger des utilisateurs de test (admin + client) en base de donnees
    pour les environnements de developpement et de test.

Securite :
    - Les mots de passe sont hashes via UserPasswordHasherInterface.
    - Un garde empeche le chargement en environnement de production
      pour eviter la creation de comptes backdoor.
===============================================================================
*/

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const ADMIN_REFERENCE = 'user-admin';
    public const USER_REFERENCE  = 'user-client';

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        #[Autowire(param: 'kernel.environment')] private string $environment,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Securite : empeche le chargement de fixtures en production
        if ($this->environment === 'prod') {
            throw new \RuntimeException('Les fixtures ne doivent jamais etre chargees en production.');
        }

        // --- Admin ---
        $admin = new User();
        $admin->setEmail('admin@volo.fr');
        $admin->setFirstName('Admin');
        $admin->setLastName('Volo');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Admin123!'));
        $manager->persist($admin);
        $this->addReference(self::ADMIN_REFERENCE, $admin);

        // --- Client ---
        $client = new User();
        $client->setEmail('client@volo.fr');
        $client->setFirstName('Marie');
        $client->setLastName('Dupont');
        $client->setPassword($this->passwordHasher->hashPassword($client, 'Client123!'));
        $manager->persist($client);
        $this->addReference(self::USER_REFERENCE, $client);

        $manager->flush();
    }
}
