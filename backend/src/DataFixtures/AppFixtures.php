<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Toutes les donnees sont chargees via les fixtures dependantes.
        // Ce fichier sert de point d'entree garantissant l'ordre d'execution.
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            BrandFixtures::class,
            SkinConcernFixtures::class,
            ProductFixtures::class,
        ];
    }
}
