<?php

/*
===============================================================================
Fixtures : RoutineFixtures
===============================================================================
Objectif :
    Peupler la table routine, restee vide depuis sa creation.

Contexte :
    L'entite Routine, son enum, son repository et sa table existaient depuis
    l'origine du projet, mais aucune fixture ne creait de routine : la table
    etait vide et le domaine inatteignable. Ces fixtures, avec
    RoutineController et RoutineCrudController, referment cet ecart.

Modele :
    Une routine n'est pas rattachee directement a une problematique de peau.
    Elle l'est INDIRECTEMENT, par les produits qu'elle contient — c'est ce que
    fait RoutineRepository::findByFilters() en joignant routine -> produits ->
    problematiques. Les produits choisis ici determinent donc les
    problematiques auxquelles chaque routine repond.
===============================================================================
*/

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\Routine;
use App\Enum\RoutineLevel;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class RoutineFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $effaclar = $this->getReference('product-effaclar', Product::class);
        $cleanser = $this->getReference('product-cleanser', Product::class);
        $lipikar = $this->getReference('product-lipikar', Product::class);

        // Debutant — deux etapes, sur la problematique « acne »
        $acne = new Routine();
        $acne->setName('Routine Anti-Imperfections');
        $acne->setLevel(RoutineLevel::BEGINNER);
        $acne->setDescription(
            'Deux etapes pour assainir sans agresser : on nettoie, puis on apaise. '
            . 'Le minimum viable quand la peau reagit a tout.'
        );
        $acne->addProduct($cleanser);
        $acne->addProduct($effaclar);
        $manager->persist($acne);

        // Intermediaire — sur la problematique « secheresse »
        $hydratation = new Routine();
        $hydratation->setName('Routine Hydratation Intense');
        $hydratation->setLevel(RoutineLevel::INTERMEDIATE);
        $hydratation->setDescription(
            'Pour les peaux qui tiraillent : un nettoyant qui ne decape pas, '
            . 'suivi d\'un baume relipidant applique sur peau encore humide.'
        );
        $hydratation->addProduct($cleanser);
        $hydratation->addProduct($lipikar);
        $manager->persist($hydratation);

        // Avance — combine les deux problematiques
        $complete = new Routine();
        $complete->setName('Routine Complete Peau Mixte');
        $complete->setLevel(RoutineLevel::ADVANCED);
        $complete->setDescription(
            'Repond a la fois aux imperfections et a la secheresse localisee. '
            . 'A reserver aux peaux deja habituees a une routine reguliere.'
        );
        $complete->addProduct($cleanser);
        $complete->addProduct($effaclar);
        $complete->addProduct($lipikar);
        $manager->persist($complete);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ProductFixtures::class,
        ];
    }
}
