<?php

namespace App\Tests\Controller;

use App\Entity\Brand;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Service\PaymentGateway\PaymentGatewayInterface;
use App\Service\PaymentGateway\PaymentIntentResult;
use App\Service\PaymentGateway\StripePaymentGateway;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/*
===============================================================================
Test fonctionnel : webhook Stripe
===============================================================================
Revise le 14/09/2026. Deux tests de la version precedente FIGEAIENT LE BUG :
`testPaymentIntentFailedMarqueEchec` exigeait qu'un refus de carte fasse passer
le paiement a `failed`. Or chez Stripe, un refus laisse le paiement ouvert ;
le client reessaie et reussit. Le succes suivant etait alors ignore — client
debite, commande jamais payee. La CI etait verte parce qu'elle verifiait
l'erreur elle-meme.
===============================================================================
*/
class WebhookStripeTest extends WebTestCase
{
    private const WEBHOOK_SECRET = 'whsec_test_secret';
    private const INTENT_ID = 'pi_test_abc123';
    private const STOCK_INITIAL = 5;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['audit_log', 'payment', 'order_item', 'shop_order', 'product', 'brand', 'user'] as $table) {
            $connection->executeStatement('TRUNCATE TABLE ' . $table);
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function creerCommandeAvecPaiement(
        PaymentStatus $status = PaymentStatus::PENDING,
        OrderStatus $orderStatus = OrderStatus::PENDING,
        string $intentId = self::INTENT_ID,
    ): Payment {
        $user = new User();
        $user->setEmail('webhook_test@volo.fr')
            ->setPassword('hashed')
            ->setFirstName('Jean')
            ->setLastName('Dupont');
        $this->em->persist($user);

        $brand = new Brand();
        $brand->setName('Marque Test');
        $this->em->persist($brand);

        $product = new Product();
        $product->setName('Serum Test')
            ->setPrice('29.90')
            ->setDescription('Un serum de test')
            ->setStock(self::STOCK_INITIAL)
            ->setBrand($brand);
        $this->em->persist($product);

        $order = new Order();
        $order->setUser($user)
            ->setStatus($orderStatus)
            ->setTotal('29.90')
            ->setStreet('12 rue de la Paix')
            ->setCity('Paris')
            ->setPostalCode('75001')
            ->setCountry('France');

        $item = new OrderItem();
        $item->setProduct($product)
            ->setProductName($product->getName())
            ->setQuantity(1)
            ->setUnitPrice('29.90');
        $order->addItem($item);

        $this->em->persist($order);

        $payment = new Payment();
        $payment->setOrderEntity($order);
        $payment->setMethod(PaymentMethod::CARD);
        $payment->setStatus($status);
        $payment->setAmount('29.90');
        $payment->setStripePaymentIntentId($intentId);
        $this->em->persist($payment);

        $this->em->flush();

        return $payment;
    }

    private function construirePayload(string $type, string $intentId): string
    {
        return json_encode([
            'id' => 'evt_test_' . bin2hex(random_bytes(8)),
            'object' => 'event',
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => $intentId,
                    'object' => 'payment_intent',
                    'amount' => 2990,
                    'currency' => 'eur',
                    'status' => $type === 'payment_intent.succeeded' ? 'succeeded' : 'requires_payment_method',
                ],
            ],
        ]);
    }

    private function construireSignature(string $payload): string
    {
        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $signature = hash_hmac('sha256', $signedPayload, self::WEBHOOK_SECRET);

        return 't=' . $timestamp . ',v1=' . $signature;
    }

    private function envoyerWebhook(string $payload, ?string $signature = null): void
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($signature !== null) {
            $headers['HTTP_STRIPE_SIGNATURE'] = $signature;
        }

        $this->client->request('POST', '/api/webhooks/stripe', [], [], $headers, $payload);
    }

    private function envoyerEvenement(string $type): void
    {
        $payload = $this->construirePayload($type, self::INTENT_ID);
        $this->envoyerWebhook($payload, $this->construireSignature($payload));
    }

    private function recharger(Payment $payment): Payment
    {
        $this->em->clear();

        return $this->em->find(Payment::class, $payment->getId());
    }

    private function stockDuProduit(Payment $payment): int
    {
        return $payment->getOrderEntity()->getItems()->first()->getProduct()->getStock();
    }

    /**
     * Remplace la passerelle Stripe par un double qui enregistre les appels.
     * Aucun test ne doit contacter Stripe : la cle de .env.test est factice.
     */
    private function passerelleFactice(): object
    {
        $factice = new class implements PaymentGatewayInterface {
            /** @var string[] */
            public array $remboursements = [];
            /** @var string[] */
            public array $annulations = [];

            public function supports(PaymentMethod $method): bool
            {
                return $method === PaymentMethod::CARD;
            }

            public function createIntent(Order $order): PaymentIntentResult
            {
                throw new \LogicException('Non utilise dans ce test.');
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

        static::getContainer()->set(StripePaymentGateway::class, $factice);

        return $factice;
    }

    // --- Signature et routage ---

    public function testWebhookSansSignatureRetourne400(): void
    {
        $payload = $this->construirePayload('payment_intent.succeeded', self::INTENT_ID);
        $this->envoyerWebhook($payload, null);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testWebhookSignatureInvalideRetourne400(): void
    {
        $payload = $this->construirePayload('payment_intent.succeeded', self::INTENT_ID);
        $this->envoyerWebhook($payload, 't=123,v1=invalide');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testEvenementInconnuRetourne200(): void
    {
        $payload = $this->construirePayload('charge.refunded', self::INTENT_ID);
        $this->envoyerWebhook($payload, $this->construireSignature($payload));

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Evenement ignore.', $response['message']);
    }

    public function testIntentIdInconnuRetourne200(): void
    {
        $payload = $this->construirePayload('payment_intent.succeeded', 'pi_inexistant');
        $this->envoyerWebhook($payload, $this->construireSignature($payload));

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Paiement inconnu, ignore.', $response['message']);
    }

    public function testWebhookExemptDuControleCsrf(): void
    {
        // Envoyer un webhook valide SANS cookie volo_csrf ni header X-Csrf-Token.
        // Si le CSRF n'est pas exempte, on recevrait 403 au lieu de 400/200.
        $payload = $this->construirePayload('payment_intent.succeeded', self::INTENT_ID);
        $this->envoyerWebhook($payload, 't=123,v1=invalide');

        // On s'attend a 400 (signature invalide), PAS 403 (CSRF).
        $this->assertResponseStatusCodeSame(400);
    }

    // --- Paiement reussi ---

    public function testPaymentIntentSucceededCaptureLePaiement(): void
    {
        $payment = $this->creerCommandeAvecPaiement();

        $this->envoyerEvenement('payment_intent.succeeded');

        $this->assertResponseStatusCodeSame(200);
        $updated = $this->recharger($payment);
        $this->assertSame(PaymentStatus::CAPTURED, $updated->getStatus());
        $this->assertSame(OrderStatus::PAID, $updated->getOrderEntity()->getStatus());
    }

    public function testPaymentIntentSucceededEstIdempotent(): void
    {
        $this->creerCommandeAvecPaiement(PaymentStatus::CAPTURED, OrderStatus::PAID);

        $this->envoyerEvenement('payment_intent.succeeded');

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Deja traite.', $response['message']);
    }

    public function testNeTransitePasCommandeDejaExpediee(): void
    {
        $payment = $this->creerCommandeAvecPaiement(PaymentStatus::PENDING, OrderStatus::SHIPPED);

        $this->envoyerEvenement('payment_intent.succeeded');

        $this->assertResponseStatusCodeSame(200);
        $updated = $this->recharger($payment);
        $this->assertSame(PaymentStatus::CAPTURED, $updated->getStatus());
        $this->assertSame(OrderStatus::SHIPPED, $updated->getOrderEntity()->getStatus());
    }

    // --- Refus de carte : le paiement reste ouvert ---

    public function testRefusDeCarteLaisseLePaiementOuvertEtLeStockReserve(): void
    {
        $payment = $this->creerCommandeAvecPaiement();

        $this->envoyerEvenement('payment_intent.payment_failed');

        $this->assertResponseStatusCodeSame(200);
        $updated = $this->recharger($payment);
        // Le coeur du correctif : un refus n'est PAS definitif chez Stripe.
        $this->assertSame(PaymentStatus::PENDING, $updated->getStatus(), 'Un refus ne doit pas fermer le paiement.');
        $this->assertSame(OrderStatus::PENDING, $updated->getOrderEntity()->getStatus());
        $this->assertSame(self::STOCK_INITIAL, $this->stockDuProduit($updated), 'Le stock reste reserve pendant que le client reessaie.');
    }

    /**
     * Le scenario exact constate avec de vrais paiements Stripe : le client
     * voit sa carte refusee, la corrige, et reessaie sur le MEME PaymentIntent
     * (ce que fait PaymentForm.jsx). Avant correction : debite, commande
     * jamais payee, stock restitue.
     */
    public function testSuccesApresRefusSurLeMemePaiementPayeLaCommande(): void
    {
        $payment = $this->creerCommandeAvecPaiement();

        $this->envoyerEvenement('payment_intent.payment_failed');
        $this->envoyerEvenement('payment_intent.succeeded');

        $this->assertResponseStatusCodeSame(200);
        $updated = $this->recharger($payment);
        $this->assertSame(PaymentStatus::CAPTURED, $updated->getStatus());
        $this->assertSame(OrderStatus::PAID, $updated->getOrderEntity()->getStatus());
        $this->assertSame(self::STOCK_INITIAL, $this->stockDuProduit($updated), 'Article vendu : le stock ne doit pas avoir ete restitue.');
    }

    /**
     * Des paiements ont ete marques `failed` par l'ancien traitement du refus
     * alors qu'ils restaient payables. Un succes ulterieur doit les capturer.
     */
    public function testUnPaiementMarqueEchoueParLAncienCodeResteCapturable(): void
    {
        $payment = $this->creerCommandeAvecPaiement(PaymentStatus::FAILED, OrderStatus::PENDING);

        $this->envoyerEvenement('payment_intent.succeeded');

        $updated = $this->recharger($payment);
        $this->assertSame(PaymentStatus::CAPTURED, $updated->getStatus());
        $this->assertSame(OrderStatus::PAID, $updated->getOrderEntity()->getStatus());
    }

    // --- Paiement recu pour une commande annulee : remboursement ---

    public function testPaiementSurCommandeAnnuleeEstRembourseEtLAdministrateurPrevenu(): void
    {
        $passerelle = $this->passerelleFactice();
        $payment = $this->creerCommandeAvecPaiement(PaymentStatus::FAILED, OrderStatus::CANCELLED);

        $this->envoyerEvenement('payment_intent.succeeded');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame([self::INTENT_ID], $passerelle->remboursements, 'Le paiement doit etre rembourse chez le prestataire.');

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'To', 'contact@volo.fr');
        $this->assertEmailSubjectContains($email, 'Remboursement automatique');

        $updated = $this->recharger($payment);
        $this->assertSame(PaymentStatus::REFUNDED, $updated->getStatus());
        $this->assertSame(OrderStatus::CANCELLED, $updated->getOrderEntity()->getStatus(), 'La commande reste annulee.');
    }

    public function testUnSuccesRejoueApresRemboursementNeRembourseQuUneFois(): void
    {
        $passerelle = $this->passerelleFactice();
        $this->creerCommandeAvecPaiement(PaymentStatus::REFUNDED, OrderStatus::CANCELLED);

        $this->envoyerEvenement('payment_intent.succeeded');

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Deja traite.', $response['message']);
        $this->assertSame([], $passerelle->remboursements, 'Stripe rejoue ses webhooks : aucun second remboursement.');
    }
}
