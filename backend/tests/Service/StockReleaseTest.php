<?php

/*
===============================================================================
Test d'intégration : réservation et restitution du stock
===============================================================================
Pourquoi ce fichier existe :
    Le stock est retiré dès la CRÉATION de la commande, au statut `pending`,
    avant tout paiement : c'est une réservation. La contrepartie —
    `OrderService::releaseStock()` — n'existait pas. Résultat constaté sur la
    base de développement : 19 commandes `pending` immobilisaient 22 unités,
    la plus ancienne datant de juin. Aucun test ne l'avait vu, parce que tous
    les tests existants suivaient le chemin qui réussit.

    Ces tests-ci suivent le chemin qui échoue : le client ne paie pas.

Ce qui est couvert :
    - La création réserve bien le stock (le bug ne vient pas de là).
    - releaseStock() restitue exactement ce qui avait été retenu.
    - releaseStock() NE FLUSHE PAS — c'est son contrat, et c'est ce qui permet
      d'en restituer plusieurs en une transaction.
    - releaseStock() N'EST PAS idempotent : appelé deux fois, il restitue deux
      fois. Ce test fige le contrat documenté. S'il vire au rouge, c'est qu'une
      garde a été ajoutée dans le service — alors les appelants qui comptaient
      sur la machine à états doivent être revus.
    - Une ligne de commande sans produit n'interrompt pas la restitution des
      autres lignes (garde défensive — voir le commentaire du test).
    - La commande app:release-stale-orders annule et restitue ; --dry-run ne
      touche à rien.
===============================================================================
*/

namespace App\Tests\Service;

use App\Entity\Brand;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class StockReleaseTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->orderService = static::getContainer()->get(OrderService::class);

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['audit_log', 'payment', 'order_item', 'shop_order', 'product_skin_concern', 'product', 'brand', 'user'] as $table) {
            $connection->executeStatement('TRUNCATE TABLE ' . $table);
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function creerProduit(int $stock, string $prix = '25.00'): Product
    {
        $brand = (new Brand())->setName('Marque ' . uniqid());

        $product = (new Product())
            ->setName('Sérum ' . uniqid())
            ->setPrice($prix)
            ->setStock($stock)
            ->setIsAvailable(true)
            ->setBrand($brand);

        $this->em->persist($brand);
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    private function creerUtilisateur(): User
    {
        $user = (new User())
            ->setEmail('client_' . uniqid() . '@volo.fr')
            ->setPassword('peu importe, haché ailleurs')
            ->setFirstName('Sophie')
            ->setLastName('Martin');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @param array<int, array{productId: int, quantity: int}> $items
     */
    private function commander(User $user, array $items): Order
    {
        return $this->orderService->createOrder([
            'shippingAddress' => [
                'street' => '12 rue de la Paix',
                'city' => 'Paris',
                'postalCode' => '75001',
            ],
            'items' => $items,
        ], $user);
    }

    /**
     * Lit le stock directement en base, sans passer par l'unité de travail.
     * Indispensable pour distinguer « modifié en mémoire » de « écrit ».
     */
    private function stockEnBase(int $productId): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT stock FROM product WHERE id = ?',
            [$productId],
        );
    }

    public function testCreerUneCommandeReserveLeStock(): void
    {
        $product = $this->creerProduit(stock: 10);
        $user = $this->creerUtilisateur();

        $this->commander($user, [['productId' => $product->getId(), 'quantity' => 3]]);

        self::assertSame(7, $this->stockEnBase($product->getId()));
    }

    public function testReleaseStockRestitueExactementLesUnitesRetenues(): void
    {
        $product = $this->creerProduit(stock: 10);
        $user = $this->creerUtilisateur();
        $order = $this->commander($user, [['productId' => $product->getId(), 'quantity' => 3]]);

        $restituees = $this->orderService->releaseStock($order);
        $this->em->flush();

        self::assertSame(3, $restituees);
        self::assertSame(10, $this->stockEnBase($product->getId()), 'Le stock doit revenir à sa valeur initiale.');
    }

    public function testReleaseStockNeFlushePas(): void
    {
        $product = $this->creerProduit(stock: 10);
        $user = $this->creerUtilisateur();
        $order = $this->commander($user, [['productId' => $product->getId(), 'quantity' => 4]]);

        $this->orderService->releaseStock($order);

        // Contrat explicite du service : l'appelant maîtrise sa transaction.
        self::assertSame(6, $this->stockEnBase($product->getId()), 'Rien ne doit être écrit avant le flush de l\'appelant.');

        $this->em->flush();

        self::assertSame(10, $this->stockEnBase($product->getId()));
    }

    public function testReleaseStockNestPasIdempotent(): void
    {
        $product = $this->creerProduit(stock: 10);
        $user = $this->creerUtilisateur();
        $order = $this->commander($user, [['productId' => $product->getId(), 'quantity' => 2]]);

        $this->orderService->releaseStock($order);
        $this->orderService->releaseStock($order);
        $this->em->flush();

        // Ce test FIGE UN CONTRAT, il ne décrit pas un comportement souhaitable.
        // releaseStock() ne se protège pas du double appel : ce sont les
        // appelants qui s'en chargent, via la garde de la machine à états
        // (un `cancel_pending` ne passe qu'une fois). Si ce test devient rouge,
        // une garde a été ajoutée dans le service — il faut alors vérifier que
        // les appelants ne comptent plus sur la machine à états pour cela.
        self::assertSame(12, $this->stockEnBase($product->getId()), 'Le double appel double la restitution — par conception.');
    }

    /**
     * Ce test est volontairement EN MÉMOIRE, sans base — et la raison mérite
     * d'être écrite, parce qu'elle corrige ce que je croyais du modèle.
     *
     * `releaseStock()` se garde d'une ligne de commande dont le produit serait
     * `null`. En cherchant à couvrir cette branche par la base, la contrainte
     * `FK_52EA1F094584665A` l'a refusée : `OrderItem::$product` est déclaré
     * `JoinColumn(nullable: false)`. Cet état est donc IMPOSSIBLE en base — la
     * garde est purement défensive.
     *
     * Deux conclusions, à ne pas confondre :
     *   - la garde ne protège d'aucun scénario réel aujourd'hui ;
     *   - elle protège en revanche d'un futur `ON DELETE SET NULL`, qui est la
     *     façon naturelle de rendre les produits supprimables sans détruire
     *     l'historique des commandes.
     * On la couvre donc pour ce qu'elle est : un comportement d'objet, pas un
     * comportement de base.
     */
    public function testReleaseStockIgnoreUneLigneSansProduitSansPerdreLesAutres(): void
    {
        $conserve = $this->creerProduit(stock: 10);

        $order = new Order();

        $orpheline = (new OrderItem())
            ->setOrderEntity($order)
            ->setQuantity(2)
            ->setUnitPrice('25.00')
            ->setProductName('Produit disparu du catalogue');
        // Pas de setProduct() : c'est tout l'objet du test.

        $reliee = (new OrderItem())
            ->setOrderEntity($order)
            ->setProduct($conserve)
            ->setQuantity(5)
            ->setUnitPrice('25.00')
            ->setProductName($conserve->getName());

        $order->addItem($orpheline);
        $order->addItem($reliee);

        $restituees = $this->orderService->releaseStock($order);

        self::assertSame(5, $restituees, 'Seule la ligne encore reliée à un produit est restituée.');
        self::assertSame(15, $conserve->getStock(), 'La ligne orpheline ne doit pas interrompre le reste.');
    }

    public function testCommandeDeBalayageAnnuleEtRestitue(): void
    {
        $product = $this->creerProduit(stock: 10);
        $user = $this->creerUtilisateur();
        $order = $this->commander($user, [['productId' => $product->getId(), 'quantity' => 3]]);

        $this->vieillir($order, minutes: 120);

        $tester = $this->executerBalayage([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(10, $this->stockEnBase($product->getId()));

        $this->em->clear();
        $rechargee = $this->em->getRepository(Order::class)->find($order->getId());
        self::assertSame(OrderStatus::CANCELLED, $rechargee->getStatus());
    }

    public function testCommandeDeBalayageEnDryRunNeModifieRien(): void
    {
        $product = $this->creerProduit(stock: 10);
        $user = $this->creerUtilisateur();
        $order = $this->commander($user, [['productId' => $product->getId(), 'quantity' => 3]]);

        $this->vieillir($order, minutes: 120);

        $tester = $this->executerBalayage(['--dry-run' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Simulation', $tester->getDisplay());
        self::assertSame(7, $this->stockEnBase($product->getId()), '--dry-run ne doit rien restituer.');

        $this->em->clear();
        $rechargee = $this->em->getRepository(Order::class)->find($order->getId());
        self::assertSame(OrderStatus::PENDING, $rechargee->getStatus(), '--dry-run ne doit pas annuler.');
    }

    public function testCommandeDeBalayageEpargneLesCommandesRecentes(): void
    {
        $product = $this->creerProduit(stock: 10);
        $user = $this->creerUtilisateur();
        $this->commander($user, [['productId' => $product->getId(), 'quantity' => 3]]);

        // Créée à l'instant : elle ne doit pas être touchée.
        $tester = $this->executerBalayage([]);

        self::assertStringContainsString('Rien a faire', $tester->getDisplay());
        self::assertSame(7, $this->stockEnBase($product->getId()));
    }

    /**
     * Le trou que StockReleaseSubscriber bouche.
     *
     * Une annulation faite a la main — c'est le geste naturel d'un
     * administrateur dans EasyAdmin — ne passait ni par la commande de
     * balayage ni par le webhook, donc ne restituait rien. Ce test reproduit
     * ce geste au plus pres : on change le statut sur l'entite, puis on
     * flushe. Aucun appel a releaseStock(), exactement comme EasyAdmin.
     */
    public function testUneAnnulationManuelleRestitueLeStock(): void
    {
        $product = $this->creerProduit(stock: 10);
        $user = $this->creerUtilisateur();
        $order = $this->commander($user, [['productId' => $product->getId(), 'quantity' => 3]]);

        self::assertSame(7, $this->stockEnBase($product->getId()), 'Reservation faite a la commande.');

        $order->setStatus(OrderStatus::CANCELLED);
        $this->em->flush();

        self::assertSame(10, $this->stockEnBase($product->getId()), 'L\'annulation doit restituer, quelle que soit son origine.');
    }

    /**
     * L'idempotence ne vient pas du listener mais de la machine a etats :
     * 'cancelled' est terminal, on n'y entre qu'une fois. Ce test verifie que
     * la barriere tient, plutot que de le supposer.
     */
    public function testUneCommandeDejaAnnuleeNeRestituePasUneSecondeFois(): void
    {
        $product = $this->creerProduit(stock: 10);
        $user = $this->creerUtilisateur();
        $order = $this->commander($user, [['productId' => $product->getId(), 'quantity' => 3]]);

        $order->setStatus(OrderStatus::CANCELLED);
        $this->em->flush();

        // Re-flusher sans changement de statut ne doit rien declencher.
        $order->setNotes('Annulee par le service client.');
        $this->em->flush();

        self::assertSame(10, $this->stockEnBase($product->getId()), 'Pas de seconde restitution.');
    }

    /**
     * Garde-fou sur la regression la plus probable : si un jour la commande de
     * balayage rappelle releaseStock() en plus de la transition, le stock
     * serait restitue deux fois et ce test virerait au rouge.
     */
    public function testLeBalayageNeRestituePasEnDouble(): void
    {
        $product = $this->creerProduit(stock: 10);
        $user = $this->creerUtilisateur();
        $order = $this->commander($user, [['productId' => $product->getId(), 'quantity' => 4]]);

        $this->vieillir($order, minutes: 120);
        $this->executerBalayage([]);

        self::assertSame(10, $this->stockEnBase($product->getId()), 'Exactement une restitution, pas deux.');
    }

    /**
     * `Order::$createdAt` est posé au constructeur et n'a pas de setter — c'est
     * voulu. Pour tester le balayage il faut donc vieillir la ligne en SQL.
     *
     * PIÈGE, éprouvé : ne pas écrire `DATE_SUB(NOW(), INTERVAL ? MINUTE)`.
     * `NOW()` est l'heure du serveur MySQL (ici Europe/Paris), alors que PHP
     * tourne en UTC — deux heures d'écart, qui annulaient exactement un
     * vieillissement de 120 minutes et faisaient passer le test pour un bug
     * de `findStalePending`. L'application ne compare jamais du temps SQL à
     * du temps PHP : Doctrine écrit `created_at` depuis PHP, et la requête le
     * compare à un `DateTimeImmutable` PHP. On reste donc du côté PHP ici
     * aussi, sans quoi le test mesure le fuseau du serveur, pas le code.
     */
    private function vieillir(Order $order, int $minutes): void
    {
        $date = (new \DateTimeImmutable())->modify(sprintf('-%d minutes', $minutes));

        $this->em->getConnection()->executeStatement(
            'UPDATE shop_order SET created_at = ? WHERE id = ?',
            [$date->format('Y-m-d H:i:s'), $order->getId()],
        );
        $this->em->clear();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function executerBalayage(array $options): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:release-stale-orders'));
        $tester->execute($options);

        return $tester;
    }
}
