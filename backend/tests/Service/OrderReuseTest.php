<?php

/*
===============================================================================
Test d'integration : reutilisation d'une commande en attente identique
===============================================================================
Pourquoi ce fichier existe :
    Recharger la page de commande, cliquer deux fois ou ouvrir deux onglets
    creait une nouvelle commande a chaque fois. Chacune reservait du stock, et
    rien ne le liberait avant le balayage des paniers abandonnes — qui n'etait
    planifie nulle part.

    OrderService::createOrder() renvoie desormais la commande en attente
    identique du meme client, sans reserver le stock une seconde fois.

Ce qui est couvert :
    - La meme demande deux fois : une seule commande, stock reserve une fois.
    - L'adresse corrigee entre-temps est mise a jour sur la commande reutilisee.
    - Les lignes sont comparees par quantites AGREGEES par produit.
    - Aucune reutilisation si : quantites differentes, commande trop ancienne
      (au-dela de la fenetre de 30 minutes), commande deja payee, ou commande
      d'un AUTRE client.
===============================================================================
*/

namespace App\Tests\Service;

use App\Entity\Brand;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderReuseTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private OrderService $orderService;
    private int $produitA;
    private int $produitB;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->orderService = static::getContainer()->get(OrderService::class);

        $connexion = $this->em->getConnection();
        $connexion->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['audit_log', 'payment', 'order_item', 'shop_order', 'product_skin_concern', 'product', 'brand', 'user'] as $table) {
            $connexion->executeStatement('TRUNCATE TABLE ' . $table);
        }
        $connexion->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $brand = (new Brand())->setName('Marque reutilisation');
        $a = (new Product())->setName('Produit A')->setPrice('10.00')->setStock(20)->setBrand($brand);
        $b = (new Product())->setName('Produit B')->setPrice('5.00')->setStock(20)->setBrand($brand);
        foreach ([$brand, $a, $b] as $entite) {
            $this->em->persist($entite);
        }
        $this->em->flush();

        $this->produitA = (int) $a->getId();
        $this->produitB = (int) $b->getId();
    }

    private function client(string $email): User
    {
        $user = (new User())->setEmail($email)->setPassword('hashed')->setFirstName('Test')->setLastName('Reutilisation');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @param list<array{int, int}> $lignes
     */
    private function commander(User $user, array $lignes, string $rue = '1 rue A'): Order
    {
        return $this->orderService->createOrder([
            'shippingAddress' => ['street' => $rue, 'city' => 'Lyon', 'postalCode' => '69001'],
            'items' => array_map(static fn (array $l): array => ['productId' => $l[0], 'quantity' => $l[1]], $lignes),
        ], $user);
    }

    private function stock(int $productId): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT stock FROM product WHERE id = ?', [$productId]);
    }

    private function nombreDeCommandes(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM shop_order');
    }

    public function testLaMemeDemandeDeuxFoisNeCreeQuUneCommandeEtNeReserveQuUneFois(): void
    {
        $user = $this->client('rechargement@volo.test');

        $premiere = $this->commander($user, [[$this->produitA, 2]]);
        $seconde = $this->commander($user, [[$this->produitA, 2]]);

        self::assertSame($premiere->getId(), $seconde->getId(), 'Recharger la page doit renvoyer la meme commande.');
        self::assertSame(1, $this->nombreDeCommandes());
        self::assertSame(18, $this->stock($this->produitA), 'Le stock ne doit etre reserve qu\'une seule fois.');
    }

    public function testLAdresseCorrigeeEstMiseAJourSurLaCommandeReutilisee(): void
    {
        $user = $this->client('adresse@volo.test');

        $this->commander($user, [[$this->produitA, 1]], '1 rue A');
        $seconde = $this->commander($user, [[$this->produitA, 1]], '99 avenue corrigee');

        $this->em->clear();
        self::assertSame('99 avenue corrigee', $this->em->find(Order::class, $seconde->getId())?->getStreet());
    }

    public function testLesLignesSontCompareesParQuantitesAgregees(): void
    {
        $user = $this->client('agregation@volo.test');

        $premiere = $this->commander($user, [[$this->produitA, 2], [$this->produitB, 1]]);
        $seconde = $this->commander($user, [[$this->produitB, 1], [$this->produitA, 1], [$this->produitA, 1]]);

        self::assertSame($premiere->getId(), $seconde->getId(), 'Meme contenu, dans un autre ordre et en lignes separees.');
    }

    public function testDesQuantitesDifferentesCreentUneNouvelleCommande(): void
    {
        $user = $this->client('quantites@volo.test');

        $premiere = $this->commander($user, [[$this->produitA, 1]]);
        $seconde = $this->commander($user, [[$this->produitA, 3]]);

        self::assertNotSame($premiere->getId(), $seconde->getId());
        self::assertSame(16, $this->stock($this->produitA), 'Deux commandes distinctes : 1 + 3 unites reservees.');
    }

    public function testUneCommandeTropAncienneNEstPasReutilisee(): void
    {
        $user = $this->client('ancienne@volo.test');
        $premiere = $this->commander($user, [[$this->produitA, 1]]);

        // Heure calculee cote PHP, comme createdAt (PHP en UTC, MySQL non).
        $il_y_a_45_minutes = (new \DateTimeImmutable('-45 minutes'))->format('Y-m-d H:i:s');
        $this->em->getConnection()->executeStatement('UPDATE shop_order SET created_at = ? WHERE id = ?', [$il_y_a_45_minutes, $premiere->getId()]);
        $this->em->clear();

        $user = $this->em->find(User::class, $user->getId());
        $seconde = $this->commander($user, [[$this->produitA, 1]]);

        self::assertNotSame($premiere->getId(), $seconde->getId(), 'Au-dela de 30 minutes, la commande est trop proche de son annulation.');
    }

    public function testUneCommandeDejaPayeeNEstPasReutilisee(): void
    {
        $user = $this->client('payee@volo.test');
        $premiere = $this->commander($user, [[$this->produitA, 1]]);

        $premiere->setStatus(OrderStatus::PAID);
        $this->em->flush();

        $seconde = $this->commander($user, [[$this->produitA, 1]]);

        self::assertNotSame($premiere->getId(), $seconde->getId(), 'Racheter le meme produit apres paiement doit creer une commande.');
    }

    public function testLaCommandeDUnAutreClientNEstJamaisReutilisee(): void
    {
        $alice = $this->client('alice@volo.test');
        $bob = $this->client('bob@volo.test');

        $deAlice = $this->commander($alice, [[$this->produitA, 1]]);
        $deBob = $this->commander($bob, [[$this->produitA, 1]]);

        self::assertNotSame($deAlice->getId(), $deBob->getId(), 'Une commande ne doit jamais etre servie a un autre client.');
        self::assertSame($bob->getId(), $deBob->getUser()?->getId());
    }
}
