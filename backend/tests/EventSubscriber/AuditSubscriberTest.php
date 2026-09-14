<?php

/*
===============================================================================
Test d'intégration : piste d'audit
===============================================================================
Pourquoi ce fichier existe :
    `AuditSubscriber` traçait les créations et rien d'autre. Les MODIFICATIONS
    — le changement de statut d'une commande, la réinitialisation d'un mot de
    passe, l'octroi d'un rôle — n'ont JAMAIS été écrites : le listener les
    persistait depuis `preUpdate`, trop tard pour que Doctrine les insère.
    L'AuditLog était créé, puis ignoré, sans la moindre erreur. La table ne
    contenait que des lignes `create`, ce qui donnait à la piste d'audit
    l'apparence exacte du bon fonctionnement.

    C'est le pire mode de panne possible pour un dispositif de traçabilité :
    silencieux, et plausible.

Ce qui est couvert :
    - Une modification de statut produit bien une ligne `update`.
    - Les valeurs avant/après sont relevées, pas seulement l'événement.
    - Un mot de passe n'atterrit JAMAIS en clair ni haché dans la table.
    - Un champ non suivi ne produit rien (le listener ne trace pas tout).
    - Une création produit une ligne `create`, sans boucler : le flush de
      `postFlush` repasse par `postFlush`, et la garde de réentrance doit
      l'arrêter. Une garde absente ferait tourner ce test indéfiniment.
===============================================================================
*/

namespace App\Tests\EventSubscriber;

use App\Entity\AuditLog;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuditSubscriberTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['audit_log', 'payment', 'order_item', 'shop_order', 'user'] as $table) {
            $connection->executeStatement('TRUNCATE TABLE ' . $table);
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function creerUtilisateur(): User
    {
        $user = (new User())
            ->setEmail('client_' . uniqid() . '@volo.fr')
            ->setPassword('$2y$13$abcdefghijklmnopqrstuv')
            ->setFirstName('Sophie')
            ->setLastName('Martin');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function creerCommande(User $user): Order
    {
        $order = (new Order())
            ->setUser($user)
            ->setStatus(OrderStatus::PENDING)
            ->setTotal('74.70')
            ->setStreet('12 rue de la Paix')
            ->setCity('Paris')
            ->setPostalCode('75001')
            ->setCountry('France');

        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }

    /**
     * @return AuditLog[]
     */
    private function journal(string $entityType, ?string $action = null): array
    {
        $criteres = ['entityType' => $entityType];
        if ($action !== null) {
            $criteres['action'] = $action;
        }

        return $this->em->getRepository(AuditLog::class)->findBy($criteres, ['id' => 'ASC']);
    }

    public function testUnChangementDeStatutEstTrace(): void
    {
        $user = $this->creerUtilisateur();
        $order = $this->creerCommande($user);

        $order->setStatus(OrderStatus::PAID);
        $this->em->flush();

        $lignes = $this->journal('Order', 'update');

        // L'assertion qui compte : il y a UNE ligne. Avant la correction, il y
        // en avait zéro — sans erreur, sans avertissement, sans trace.
        self::assertCount(1, $lignes, 'Une modification de statut doit produire exactement une ligne d\'audit.');
        self::assertSame('status', $lignes[0]->getField());
        self::assertSame($order->getId(), $lignes[0]->getEntityId());
    }

    public function testLesValeursAvantEtApresSontRelevees(): void
    {
        $user = $this->creerUtilisateur();
        $order = $this->creerCommande($user);

        $order->setStatus(OrderStatus::PAID);
        $this->em->flush();

        $ligne = $this->journal('Order', 'update')[0];

        // Sans les deux valeurs, la piste dit qu'il s'est passé quelque chose
        // mais pas quoi — ce qui ne sert à rien lors d'un litige.
        self::assertSame(OrderStatus::PENDING->value, $ligne->getOldValue());
        self::assertSame(OrderStatus::PAID->value, $ligne->getNewValue());
    }

    public function testPlusieursChangementsSuccessifsProduisentPlusieursLignes(): void
    {
        $user = $this->creerUtilisateur();
        $order = $this->creerCommande($user);

        $order->setStatus(OrderStatus::PAID);
        $this->em->flush();

        $order->setStatus(OrderStatus::SHIPPED);
        $this->em->flush();

        $lignes = $this->journal('Order', 'update');

        self::assertCount(2, $lignes);
        self::assertSame(OrderStatus::PAID->value, $lignes[1]->getOldValue());
        self::assertSame(OrderStatus::SHIPPED->value, $lignes[1]->getNewValue());
    }

    public function testUnMotDePasseNatterritJamaisDansLaTableDaudit(): void
    {
        $user = $this->creerUtilisateur();

        $nouveauHash = '$2y$13$zyxwvutsrqponmlkjihgfe';
        $user->setPassword($nouveauHash);
        $this->em->flush();

        $lignes = $this->journal('User', 'update');

        self::assertCount(1, $lignes);
        self::assertSame('password', $lignes[0]->getField());

        // Le point de tout l'exercice : la piste d'audit doit dire QU'IL a
        // changé, jamais POUR QUOI. Une table d'audit lisible par l'admin qui
        // recopierait les empreintes serait une seconde base de mots de passe.
        self::assertSame('[hashed]', $lignes[0]->getNewValue());
        self::assertSame('[hashed]', $lignes[0]->getOldValue());
        self::assertStringNotContainsString($nouveauHash, (string) $lignes[0]->getNewValue());
    }

    public function testUnChangementDeRolesEstTrace(): void
    {
        $user = $this->creerUtilisateur();

        $user->setRoles(['ROLE_ADMIN']);
        $this->em->flush();

        $lignes = $this->journal('User', 'update');

        self::assertCount(1, $lignes);
        self::assertSame('roles', $lignes[0]->getField());
        self::assertStringContainsString('ROLE_ADMIN', (string) $lignes[0]->getNewValue());
    }

    public function testUnChampNonSuiviNeProduitAucuneLigne(): void
    {
        $user = $this->creerUtilisateur();
        $avant = \count($this->journal('User', 'update'));

        $user->setFirstName('Camille');
        $this->em->flush();

        // Le listener a une liste explicite de champs sensibles. S'il se met à
        // tout tracer, la table devient illisible et la piste perd sa valeur.
        self::assertCount($avant, $this->journal('User', 'update'));
    }

    public function testUneCreationEstTraceeSansBoucler(): void
    {
        $user = $this->creerUtilisateur();
        $this->creerCommande($user);

        // Les créations sont écrites depuis postFlush, donc par un flush
        // imbriqué qui repasse lui-même par postFlush. Si la file n'était pas
        // vidée AVANT ce flush, l'écriture se relancerait sans fin : ce test ne
        // se terminerait pas, et la table se remplirait de doublons.
        self::assertCount(1, $this->journal('User', 'create'));
        self::assertCount(1, $this->journal('Order', 'create'));
    }
}
