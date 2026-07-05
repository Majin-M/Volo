<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\Brand;      // <--- Ajout de l'import
use App\Entity\SkinConcern; // <--- Ajout de l'import
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // On ajoute le 2ème argument (la classe) à chaque getReference
        $brandLrp = $this->getReference('brand-lrp', Brand::class);
        $brandCerave = $this->getReference('brand-cerave', Brand::class);
        $concernAcne = $this->getReference('concern-acne', SkinConcern::class);
        $concernDry = $this->getReference('concern-dry', SkinConcern::class);

        // Produit 1
        $p1 = new Product();
        $p1->setName('Effaclar H Iso-Biome');
        $p1->setDescription('Soit apaisant pour les peaux sujettes à l\'acné.');
        $p1->setPrice('14.50');
        $p1->setIsAvailable(true);
        $p1->setBrand($brandLrp);
        $p1->addSkinConcern($concernAcne);
        $manager->persist($p1);

        // Produit 2
        $p2 = new Product();
        $p2->setName('Hydrating Cleanser');
        $p2->setDescription('Nettoyant hydratant pour peaux sèches à très sèches.');
        $p2->setPrice('16.00');
        $p2->setIsAvailable(true);
        $p2->setBrand($brandCerave);
        $p2->addSkinConcern($concernDry);
        $manager->persist($p2);

        // Produit 3
        $p3 = new Product();
        $p3->setName('Lipikar Baume AP+');
        $p3->setDescription('Baume relipidant anti-grattage.');
        $p3->setPrice('19.90');
        $p3->setIsAvailable(true);
        $p3->setBrand($brandLrp);
        $p3->addSkinConcern($concernDry);
        $manager->persist($p3);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            BrandFixtures::class,
            SkinConcernFixtures::class,
        ];
    }
}