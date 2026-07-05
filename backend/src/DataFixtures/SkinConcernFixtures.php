<?php

namespace App\DataFixtures;

use App\Entity\SkinConcern;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SkinConcernFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $concern1 = new SkinConcern();
        $concern1->setName('Acné');
        $concern1->setSlug('acne');
        $concern1->setDescription('Peaux à tendance acnéique.');
        $manager->persist($concern1);
        $this->addReference('concern-acne', $concern1);

        $concern2 = new SkinConcern();
        $concern2->setName('Sécheresse');
        $concern2->setSlug('secheresse');
        $concern2->setDescription('Peaux manquant d\'hydratation.');
        $manager->persist($concern2);
        $this->addReference('concern-dry', $concern2);

        $manager->flush();
    }
}