<?php

/*
===============================================================================
Test d'intégration : Order <-> Payment
===============================================================================
Objectif :
    Couvrir la correction de docs/MODELE_DONNEES.md §6.1 et §6.2 — la
    suppression du doublon de statut de paiement et l'inversion du cascade.

Pourquoi ce fichier existe :
    docs/CORRECTION.md annonçait « verifier.php couvre 20 assertions, toutes
    vertes ». Ce fichier n'a jamais existé dans le dépôt et ce résultat n'a
    jamais été obtenu — pendant que la migration correspondante, elle, plantait
    dès sa première requête. Une vérification annoncée mais absente est pire
    qu'une vérification manquante : elle éteint la question.

    Ces tests-ci tournent contre Doctrine et la vraie base, pas sur des stubs.
    Ils couvrent ce que CORRECTION.md demandait de « contrôler à la main ».

Ce qui est couvert :
    - Dérivation de getPaymentStatus() / getPaymentMethod() depuis Payment.
    - Préservation du contrat d'API (clés paymentStatus / paymentMethod).
    - Non-exposition de $payment (référence circulaire + fuite de clientSecret).
    - ON DELETE CASCADE : supprimer une commande emporte son paiement.
    - Sens du cascade : supprimer un paiement NE supprime PAS la commande.
===============================================================================
*/

namespace App\Tests\Entity;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class OrderPaymentTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['payment', 'order_item', 'shop_order', 'user'] as $table) {
            $connection->executeStatement('TRUNCATE TABLE ' . $table);
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function creerCommande(): Order
    {
        $user = (new User())
            ->setEmail('client_' . uniqid() . '@volo.fr')
            ->setPassword('peu importe, haché ailleurs')
            ->setFirstName('Sophie')
            ->setLastName('Martin');

        $order = (new Order())
            ->setUser($user)
            ->setStatus(OrderStatus::PENDING)
            ->setTotal('74.70')
            ->setStreet('12 rue de la Paix')
            ->setCity('Paris')
            ->setPostalCode('75001')
            ->setCountry('France');

        $this->em->persist($user);
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }

    private function creerPaiement(Order $order, PaymentStatus $status): Payment
    {
        $payment = (new Payment())
            ->setOrderEntity($order)
            ->setStatus($status)
            ->setMethod(PaymentMethod::CARD)
            ->setAmount('74.70')
            ->setClientSecret('pi_3Oxxxx_secret_NEDOITPASFUIR');

        $this->em->persist($payment);
        $this->em->flush();

        return $payment;
    }

    /**
     * Sans paiement, les getters dérivés valent null — exactement ce que
     * faisait l'ancienne colonne nullable. C'est ce qui préserve le contrat.
     */
    public function testStatutNullTantQuAucunPaiementNExiste(): void
    {
        $order = $this->creerCommande();

        $this->assertNull($order->getPaymentStatus());
        $this->assertNull($order->getPaymentMethod());
    }

    /**
     * Le cœur de la correction : Payment fait autorité, Order ne fait que lire.
     */
    public function testLeStatutEstDeriveDePayment(): void
    {
        $order = $this->creerCommande();
        $this->creerPaiement($order, PaymentStatus::CAPTURED);

        $this->em->clear();
        $rechargee = $this->em->getRepository(Order::class)->find($order->getId());

        $this->assertSame(PaymentStatus::CAPTURED, $rechargee->getPaymentStatus());
        $this->assertSame(PaymentMethod::CARD, $rechargee->getPaymentMethod());
    }

    /**
     * Une seule source de vérité : écrire sur Payment change ce que lit Order,
     * sans aucune synchronisation à maintenir. C'est tout l'intérêt de l'option
     * A retenue en DIAGRAMME_ETATS.md §3.
     */
    public function testEcrireSurPaymentSuffitAChangerCeQueLitOrder(): void
    {
        $order = $this->creerCommande();
        $payment = $this->creerPaiement($order, PaymentStatus::PENDING);

        $this->assertSame(PaymentStatus::PENDING, $order->getPaymentStatus());

        $payment->setStatus(PaymentStatus::CAPTURED);
        $this->em->flush();
        $this->em->clear();

        $rechargee = $this->em->getRepository(Order::class)->find($order->getId());
        $this->assertSame(PaymentStatus::CAPTURED, $rechargee->getPaymentStatus());
    }

    /**
     * CORRECTION.md : « Le contrat d'API est identique. Le JSON renvoyé par
     * GET /api/orders contient toujours les clés paymentStatus et
     * paymentMethod ». Vérifié plutôt qu'affirmé.
     */
    public function testLeContratDApiEstPreserve(): void
    {
        $order = $this->creerCommande();
        $this->creerPaiement($order, PaymentStatus::CAPTURED);

        /** @var SerializerInterface $serializer */
        $serializer = static::getContainer()->get('serializer');
        $json = $serializer->serialize($order, 'json', ['groups' => 'order:read']);
        $data = json_decode($json, true);

        $this->assertArrayHasKey('paymentStatus', $data);
        $this->assertArrayHasKey('paymentMethod', $data);
        $this->assertSame('captured', $data['paymentStatus']);
        $this->assertSame('card', $data['paymentMethod']);
    }

    /**
     * $payment n'est pas exposé : cela créerait une référence circulaire
     * (Order → Payment → Order) et ferait fuiter clientSecret dans l'API.
     */
    public function testPaymentNEstPasSerialiseEtClientSecretNeFuitPas(): void
    {
        $order = $this->creerCommande();
        $this->creerPaiement($order, PaymentStatus::CAPTURED);

        $serializer = static::getContainer()->get('serializer');
        $json = $serializer->serialize($order, 'json', ['groups' => 'order:read']);

        $this->assertArrayNotHasKey('payment', json_decode($json, true));
        $this->assertStringNotContainsString('clientSecret', $json);
        $this->assertStringNotContainsString('NEDOITPASFUIR', $json);
    }

    /**
     * §6.2 : le bon sens du cascade. Supprimer la commande emporte le paiement.
     *
     * Sans le ON DELETE CASCADE au niveau base, cette suppression lèverait une
     * violation de contrainte (payment.order_id est NOT NULL) — une 500 opaque
     * dans EasyAdmin plutôt qu'un comportement défini.
     */
    public function testSupprimerLaCommandeEmporteSonPaiement(): void
    {
        $order = $this->creerCommande();
        $this->creerPaiement($order, PaymentStatus::CAPTURED);
        $orderId = $order->getId();

        $this->em->remove($order);
        $this->em->flush();

        $this->assertSame(0, $this->compterPaiementsDeLaCommande($orderId));
        $this->assertNull($this->em->getRepository(Order::class)->find($orderId));
    }

    /**
     * §6.2, le défaut d'origine : le cascade était déclaré de Payment VERS
     * Order, donc supprimer un paiement supprimait la commande et son
     * historique. Ce test échouerait si quelqu'un le réintroduisait.
     */
    public function testSupprimerUnPaiementNeSupprimePasLaCommande(): void
    {
        $order = $this->creerCommande();
        $payment = $this->creerPaiement($order, PaymentStatus::FAILED);
        $orderId = $order->getId();

        $this->em->remove($payment);
        $this->em->flush();
        $this->em->clear();

        $survivante = $this->em->getRepository(Order::class)->find($orderId);

        $this->assertNotNull($survivante, 'Supprimer un paiement NE DOIT PAS supprimer la commande.');
        $this->assertNull($survivante->getPaymentStatus());
    }

    /**
     * CORRECTION.md désigne ce point comme « le plus susceptible de casser » :
     * OrderCrudController::setSearchFields() visait 'paymentStatus', une
     * colonne qui n'existe plus, et vise désormais 'payment.status' — une
     * traversée d'association.
     *
     * L'enjeu est qu'une traversée invalide produit une ERREUR SQL, pas un
     * résultat vide : le back-office casse en 500 au premier mot-clé tapé.
     * Ce test rejoue le DQL qu'EasyAdmin construit pour ce champ.
     */
    public function testLaRechercheAdminPeutTraverserVersPaymentStatus(): void
    {
        $order = $this->creerCommande();
        $this->creerPaiement($order, PaymentStatus::CAPTURED);
        $this->em->clear();

        $resultats = $this->em->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o')
            ->leftJoin('o.payment', 'p')
            ->where('p.status = :statut')
            ->setParameter('statut', PaymentStatus::CAPTURED)
            ->getQuery()
            ->getResult();

        $this->assertCount(1, $resultats);
        $this->assertSame($order->getId(), $resultats[0]->getId());
    }

    /**
     * Le champ 'payment.status' déclaré dans setSearchFields() doit correspondre
     * à une association réellement mappée. Si quelqu'un renomme la propriété,
     * ce test échoue ici plutôt qu'en production au premier mot-clé.
     */
    public function testLAssociationPaymentEstBienMappeeSurOrder(): void
    {
        $metadata = $this->em->getClassMetadata(Order::class);

        $this->assertTrue($metadata->hasAssociation('payment'));
        $this->assertSame(
            'orderEntity',
            $metadata->getAssociationMapping('payment')['mappedBy'],
            'Order.payment doit rester le côté inverse : la clé étrangère vit sur Payment.'
        );
    }

    private function compterPaiementsDeLaCommande(int $orderId): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM payment WHERE order_id = ?',
            [$orderId]
        );
    }
}
