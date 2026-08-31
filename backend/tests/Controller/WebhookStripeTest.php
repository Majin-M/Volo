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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WebhookStripeTest extends WebTestCase
{
    private const WEBHOOK_SECRET = 'whsec_test_secret';
    private const INTENT_ID = 'pi_test_abc123';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['payment', 'order_item', 'shop_order', 'product', 'brand', 'user'] as $table) {
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

    // --- Tests ---

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

    public function testPaymentIntentSucceededCaptureLePaiement(): void
    {
        $payment = $this->creerCommandeAvecPaiement();
        $payload = $this->construirePayload('payment_intent.succeeded', self::INTENT_ID);
        $signature = $this->construireSignature($payload);

        $this->envoyerWebhook($payload, $signature);

        $this->assertResponseStatusCodeSame(200);

        $this->em->clear();
        $updated = $this->em->find(Payment::class, $payment->getId());
        $this->assertSame(PaymentStatus::CAPTURED, $updated->getStatus());
        $this->assertSame(OrderStatus::PAID, $updated->getOrderEntity()->getStatus());
    }

    public function testPaymentIntentSucceededEstIdempotent(): void
    {
        $this->creerCommandeAvecPaiement(PaymentStatus::CAPTURED, OrderStatus::PAID);
        $payload = $this->construirePayload('payment_intent.succeeded', self::INTENT_ID);
        $signature = $this->construireSignature($payload);

        $this->envoyerWebhook($payload, $signature);

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Deja traite.', $response['message']);
    }

    public function testPaymentIntentFailedMarqueEchec(): void
    {
        $payment = $this->creerCommandeAvecPaiement();
        $payload = $this->construirePayload('payment_intent.payment_failed', self::INTENT_ID);
        $signature = $this->construireSignature($payload);

        $this->envoyerWebhook($payload, $signature);

        $this->assertResponseStatusCodeSame(200);

        $this->em->clear();
        $updated = $this->em->find(Payment::class, $payment->getId());
        $this->assertSame(PaymentStatus::FAILED, $updated->getStatus());
        $this->assertSame(OrderStatus::PENDING, $updated->getOrderEntity()->getStatus());
    }

    public function testPaymentIntentFailedEstIdempotent(): void
    {
        $this->creerCommandeAvecPaiement(PaymentStatus::FAILED);
        $payload = $this->construirePayload('payment_intent.payment_failed', self::INTENT_ID);
        $signature = $this->construireSignature($payload);

        $this->envoyerWebhook($payload, $signature);

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Deja traite.', $response['message']);
    }

    public function testEvenementInconnuRetourne200(): void
    {
        $payload = $this->construirePayload('charge.refunded', self::INTENT_ID);
        $signature = $this->construireSignature($payload);

        $this->envoyerWebhook($payload, $signature);

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Evenement ignore.', $response['message']);
    }

    public function testIntentIdInconnuRetourne200(): void
    {
        $payload = $this->construirePayload('payment_intent.succeeded', 'pi_inexistant');
        $signature = $this->construireSignature($payload);

        $this->envoyerWebhook($payload, $signature);

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

    public function testNeTransitePasCommandeDejaExpediee(): void
    {
        $payment = $this->creerCommandeAvecPaiement(
            PaymentStatus::PENDING,
            OrderStatus::SHIPPED,
        );
        $payload = $this->construirePayload('payment_intent.succeeded', self::INTENT_ID);
        $signature = $this->construireSignature($payload);

        $this->envoyerWebhook($payload, $signature);

        $this->assertResponseStatusCodeSame(200);

        $this->em->clear();
        $updated = $this->em->find(Payment::class, $payment->getId());
        $this->assertSame(PaymentStatus::CAPTURED, $updated->getStatus());
        $this->assertSame(OrderStatus::SHIPPED, $updated->getOrderEntity()->getStatus());
    }
}
