<?php

namespace App\DataFixtures;

use App\Entity\Brand;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class BrandFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $brand1 = new Brand();
        $brand1->setName('La Roche-Posay');
        $brand1->setLogoUrl('/media/brands/lrp-logo.svg');
        $manager->persist($brand1);
        $this->addReference('brand-lrp', $brand1); // On sauvegarde une référence pour l'utiliser plus tard

        $brand2 = new Brand();
        $brand2->setName('CeraVe');
        $brand2->setLogoUrl('/media/brands/cerave-logo.svg');
        $manager->persist($brand2);
        $this->addReference('brand-cerave', $brand2);

        $manager->flush();
    }
}