<?php

/*
===============================================================================
Test d'integration : initiation et reglement des paiements
===============================================================================
Couvre les deux services corriges le 14/09/2026 apres des tests avec de vrais
paiements Stripe :

  - PaymentService : un second appel pour la meme commande rendait 500
    (contrainte d'unicite sur payment.order_id) — un client dont la carte
    avait ete refusee ne pouvait plus payer apres avoir recharge la page.

  - PaymentCancellationService : annuler une commande laissait son paiement
    ouvert chez Stripe, et rien ne remboursait un paiement recu apres coup.

La passerelle est un double : aucun test ne contacte Stripe.
===============================================================================
*/

namespace App\Tests\Service;

use App\Entity\Brand;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Service\PaymentCancellationService;
use App\Service\PaymentGateway\PaymentGatewayInterface;
use App\Service\PaymentGateway\PaymentGatewayResolver;
use App\Service\PaymentGateway\PaymentIntentResult;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\MailerInterface;

class PaymentSettlementTest extends WebTestCase
{
    private EntityManagerInterface $em;

    /** @var PaymentGatewayInterface&object{creations: int, annulations: string[], remboursements: string[]} */
    private object $passerelle;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['audit_log', 'payment', 'order_item', 'shop_order', 'product', 'brand', 'user'] as $table) {
            $connection->executeStatement('TRUNCATE TABLE ' . $table);
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $this->passerelle = new class implements PaymentGatewayInterface {
            public int $creations = 0;
            /** @var string[] */
            public array $annulations = [];
            /** @var string[] */
            public array $remboursements = [];

            public function supports(PaymentMethod $method): bool
            {
                return $method === PaymentMethod::CARD;
            }

            public function createIntent(Order $order): PaymentIntentResult
            {
                ++$this->creations;
                $id = 'pi_factice_' . $this->creations;

                return new PaymentIntentResult($id, $id . '_secret_x', $order->getTotal());
            }

            public function cancelIntent(string $externalId): void
            {
                $this->annulations[] = $externalId;
            }

            public function refund(string $externalId): void
            {
                $this->remboursements[] = $externalId;
            }
        };
    }

    private function paymentService(): PaymentService
    {
        return new PaymentService(new PaymentGatewayResolver([$this->passerelle]), $this->em);
    }

    private function cancellationService(): PaymentCancellationService
    {
        $container = static::getContainer();

        return new PaymentCancellationService(
            new PaymentGatewayResolver([$this->passerelle]),
            $container->get(MailerInterface::class),
            $container->get('logger'),
            $container->get('state_machine.payment'),
            'admin@volo.test',
            'no-reply@volo.fr',
        );
    }

    private function creerCommande(OrderStatus $status = OrderStatus::PENDING): Order
    {
        $user = (new User())
            ->setEmail('client_' . uniqid() . '@volo.fr')
            ->setPassword('hashed')
            ->setFirstName('Sophie')
            ->setLastName('Martin');
        $brand = (new Brand())->setName('Marque ' . uniqid());
        $product = (new Product())
            ->setName('Serum ' . uniqid())
            ->setPrice('29.90')
            ->setStock(5)
            ->setBrand($brand);

        $order = (new Order())
            ->setUser($user)
            ->setStatus($status)
            ->setTotal('29.90')
            ->setStreet('12 rue de la Paix')
            ->setCity('Paris')
            ->setPostalCode('75001')
            ->setCountry('France');
        $order->addItem(
            (new OrderItem())->setProduct($product)->setProductName('Serum')->setQuantity(1)->setUnitPrice('29.90')
        );

        foreach ([$user, $brand, $product, $order] as $entite) {
            $this->em->persist($entite);
        }
        $this->em->flush();

        return $order;
    }

    private function ajouterPaiement(Order $order, PaymentStatus $status, ?string $intentId = 'pi_existant'): Payment
    {
        $payment = (new Payment())
            ->setOrderEntity($order)
            ->setMethod(PaymentMethod::CARD)
            ->setStatus($status)
            ->setAmount('29.90')
            ->setStripePaymentIntentId($intentId);
        $this->em->persist($payment);
        $this->em->flush();

        return $payment;
    }

    /**
     * Rejoue ce que fait une nouvelle requete HTTP : un gestionnaire d'entites
     * vide, qui relit la commande — et donc son paiement — depuis la base.
     */
    private function recharger(Order $order): Order
    {
        $id = $order->getId();
        $this->em->clear();

        return $this->em->find(Order::class, $id);
    }

    // --- PaymentService : idempotence ---

    public function testUnSecondAppelRenvoieLeMemePaiementSansEnCreerUnAutre(): void
    {
        $order = $this->creerCommande();

        $premier = $this->paymentService()->initiatePayment($order, PaymentMethod::CARD);
        $second = $this->paymentService()->initiatePayment($this->recharger($order), PaymentMethod::CARD);

        $this->assertSame($premier->getId(), $second->getId());
        $this->assertSame($premier->getClientSecret(), $second->getClientSecret(), 'Le client doit reprendre le meme formulaire de paiement.');
        $this->assertSame(1, $this->passerelle->creations, 'Aucun PaymentIntent orphelin chez le prestataire.');
    }

    public function testUneCommandeDejaPayeeNePeutPasEtrePayeeUneSecondeFois(): void
    {
        $order = $this->creerCommande(OrderStatus::PAID);

        $this->expectException(\DomainException::class);
        $this->paymentService()->initiatePayment($order, PaymentMethod::CARD);
    }

    public function testUnPaiementDejaFinaliseEstRefuse(): void
    {
        $order = $this->creerCommande();
        $this->ajouterPaiement($order, PaymentStatus::CAPTURED);

        $this->expectException(\DomainException::class);
        $this->paymentService()->initiatePayment($this->recharger($order), PaymentMethod::CARD);
    }

    // --- PaymentCancellationService ---

    public function testAnnulerUneCommandeFermeSonPaiementOuvert(): void
    {
        $order = $this->creerCommande();
        $this->ajouterPaiement($order, PaymentStatus::PENDING, 'pi_ouvert');
        $order = $this->recharger($order);

        $this->cancellationService()->settleForCancelledOrder($order);
        $this->em->flush();

        $this->assertSame(['pi_ouvert'], $this->passerelle->annulations, 'Le client ne doit plus pouvoir payer une commande annulee.');
        $this->assertSame([], $this->passerelle->remboursements);
        $this->assertSame(PaymentStatus::FAILED, $this->recharger($order)->getPayment()->getStatus());
        $this->assertEmailCount(0);
    }

    public function testAnnulerUneCommandePayeeRembourseEtPrevientLAdministrateur(): void
    {
        $order = $this->creerCommande(OrderStatus::PAID);
        $this->ajouterPaiement($order, PaymentStatus::CAPTURED, 'pi_encaisse');
        $order = $this->recharger($order);

        $this->cancellationService()->settleForCancelledOrder($order);
        $this->em->flush();

        $this->assertSame(['pi_encaisse'], $this->passerelle->remboursements);
        $this->assertSame(PaymentStatus::REFUNDED, $this->recharger($order)->getPayment()->getStatus());

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'To', 'admin@volo.test');
        $this->assertEmailTextBodyContains($email, 'pi_encaisse');
    }

    public function testUneCommandeSansPaiementSAnnuleSansRienToucher(): void
    {
        $order = $this->creerCommande();

        $this->cancellationService()->settleForCancelledOrder($order);

        $this->assertSame([], $this->passerelle->annulations);
        $this->assertSame([], $this->passerelle->remboursements);
        $this->assertEmailCount(0);
    }

    public function testUnPaiementDejaRembourseNEstPasRembourseDeNouveau(): void
    {
        $order = $this->creerCommande(OrderStatus::PAID);
        $this->ajouterPaiement($order, PaymentStatus::REFUNDED, 'pi_rembourse');

        $this->cancellationService()->settleForCancelledOrder($this->recharger($order));

        $this->assertSame([], $this->passerelle->remboursements);
        $this->assertSame([], $this->passerelle->annulations);
    }
}
