<?php

/*
===============================================================================
Test fonctionnel : protection CSRF
===============================================================================
Objectif :
    Prouver que le double-submit cookie de CsrfProtectionSubscriber protège
    réellement l'API.

Pourquoi ce fichier existe :
    docs/CONTRAT_API.md §2 le disait sans détour : « Jamais vérifié. Aucun test
    ne confirme qu'un POST /api/orders sans en-tête X-Csrf-Token retourne bien
    403. Le mécanisme est écrit, sa protection réelle est SUPPOSÉE. C'est
    exactement le genre de sécurité qu'on croit avoir. »

    docs/STRATEGIE_TESTS.md §10 en faisait le premier test à écrire. Le voici.

Ce que la protection CSRF défend :
    Le JWT vit dans un cookie HttpOnly (CONTRAT_API.md §1). Le navigateur
    l'envoie AUTOMATIQUEMENT, y compris sur une requête déclenchée depuis un
    site tiers. Le CORS n'y change rien : il empêche de LIRE la réponse, pas
    de l'ENVOYER — et une commande créée l'est déjà avant tout contrôle CORS.
    Le seul rempart est donc l'en-tête X-Csrf-Token, qu'un site tiers ne peut
    pas fabriquer puisqu'il ne peut pas lire le cookie volo_csrf.

Ordre d'exécution, à connaître (constaté en écrivant ces tests) :
    CsrfProtectionSubscriber écoute kernel.request SANS priorité, donc à 0.
    Deux écouteurs Symfony passent avant lui :

        RouterListener (32)  ->  route inconnue    = 404, jamais 403
        Firewall (8)         ->  non authentifié   = 401, jamais 403
        CsrfProtectionSubscriber (0)               = 403

    Le contrôle CSRF arrive donc EN DERNIER. Ce n'est pas un défaut : une
    route inexistante ne fait rien, et une requête non authentifiée est déjà
    rejetée. Mais il faut le savoir pour tester la bonne chose — ces tests
    s'authentifient d'abord et visent des routes réelles, sinon ils
    prouveraient que le routeur ou le firewall fonctionnent, pas le CSRF.
===============================================================================
*/

namespace App\Tests\Security;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CsrfProtectionTest extends WebTestCase
{
    private const CLIENT_IP = '127.0.0.1';
    private const MOT_DE_PASSE = 'P@ssw0rd123!';
    private const EMAIL = 'csrf_client@volo.fr';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');

        $connection = $em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['payment', 'order_item', 'shop_order', 'user'] as $table) {
            $connection->executeStatement('TRUNCATE TABLE ' . $table);
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        // Les limiteurs comptent par IP et sont partagés entre exécutions :
        // sans reset, ces tests virent au 429 dès le second passage.
        foreach (['limiter.login_attempts', 'limiter.register_attempts'] as $limiterId) {
            $container->get($limiterId)->create(self::CLIENT_IP)->reset();
        }

        $hasher = $container->get('security.user_password_hasher');
        $user = new User();
        $user->setEmail(self::EMAIL)
            ->setPassword($hasher->hashPassword($user, self::MOT_DE_PASSE))
            ->setFirstName('Sophie')
            ->setLastName('Martin');

        $em->persist($user);
        $em->flush();
    }

    /**
     * Se connecte et retourne le jeton CSRF posé par AuthController.
     * Le client conserve les cookies (volo_token + volo_csrf) pour la suite.
     */
    private function seConnecter(): string
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => self::EMAIL, 'password' => self::MOT_DE_PASSE])
        );

        $this->assertResponseIsSuccessful('La connexion doit réussir pour que les tests CSRF aient un sens.');

        $jeton = $this->client->getCookieJar()->get('volo_csrf');
        $this->assertNotNull($jeton, 'Le cookie volo_csrf doit être posé à la connexion.');

        return $jeton->getValue();
    }

    private function commandeValide(): string
    {
        return json_encode([
            'items' => [['productId' => 1, 'quantity' => 1]],
            'shippingAddress' => [
                'street' => '12 rue de la Paix',
                'city' => 'Paris',
                'postalCode' => '75001',
                'country' => 'France',
            ],
        ]);
    }

    /**
     * LE test. Un client authentifié, mais sans en-tête X-Csrf-Token :
     * c'est exactement ce que produit un formulaire hébergé sur un site tiers.
     */
    public function testPostOrdersSansEnteteCsrfEstRejete(): void
    {
        $this->seConnecter();

        $this->client->request(
            'POST',
            '/api/orders',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->commandeValide()
        );

        $this->assertResponseStatusCodeSame(
            403,
            'Un POST /api/orders sans X-Csrf-Token DOIT être rejeté en 403.'
        );

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('CSRF', $data['error']);
    }

    /**
     * Un site tiers peut faire ENVOYER le cookie par le navigateur, mais ne
     * peut pas le LIRE (politique de même origine) : il ne peut donc que
     * deviner l'en-tête. Une valeur qui ne correspond pas doit échouer.
     */
    public function testPostOrdersAvecEnteteCsrfIncorrectEstRejete(): void
    {
        $this->seConnecter();

        $this->client->request(
            'POST',
            '/api/orders',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => 'jeton-invente-par-un-attaquant',
            ],
            $this->commandeValide()
        );

        $this->assertResponseStatusCodeSame(
            403,
            'Un X-Csrf-Token qui ne correspond pas au cookie DOIT être rejeté.'
        );
    }

    /**
     * Le pendant du test précédent : avec le bon jeton, la requête doit
     * FRANCHIR le contrôle CSRF.
     *
     * On n'affirme pas qu'elle réussit — la commande peut échouer en 400 pour
     * des raisons métier (produit inexistant en base de test). On affirme
     * seulement qu'elle n'est plus arrêtée par le CSRF. Sans ce test, un
     * subscriber qui renverrait 403 à TOUT LE MONDE passerait les deux tests
     * ci-dessus avec succès.
     */
    public function testPostOrdersAvecLeBonJetonFranchitLeControleCsrf(): void
    {
        $jeton = $this->seConnecter();

        $this->client->request(
            'POST',
            '/api/orders',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $jeton,
            ],
            $this->commandeValide()
        );

        $this->assertNotSame(
            403,
            $this->client->getResponse()->getStatusCode(),
            'Avec le bon jeton CSRF, la requête ne doit plus être bloquée par le contrôle CSRF.'
        );
    }

    /**
     * Les méthodes sûres ne modifient rien : les soumettre au contrôle CSRF
     * n'apporterait aucune sécurité et casserait la navigation.
     */
    public function testMethodeSureNonSoumiseAuControleCsrf(): void
    {
        $this->seConnecter();

        $this->client->request('GET', '/api/orders');

        $this->assertNotSame(
            403,
            $this->client->getResponse()->getStatusCode(),
            'Un GET ne doit jamais être bloqué par le contrôle CSRF.'
        );
    }

    /**
     * Exemption nécessaire : au moment du login, le cookie volo_csrf n'existe
     * pas encore — c'est la réponse du login qui le pose. Sans exemption,
     * personne ne pourrait jamais se connecter.
     */
    public function testLoginEstExemptDuControleCsrf(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => self::EMAIL, 'password' => self::MOT_DE_PASSE])
        );

        $this->assertResponseIsSuccessful();
        $this->assertNotSame(403, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Même raison que le login : aucun cookie CSRF n'existe encore.
     */
    public function testRegisterEstExemptDuControleCsrf(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'nouveau_csrf@volo.fr',
                'password' => self::MOT_DE_PASSE,
                'firstName' => 'Jean',
                'lastName' => 'Dupont',
            ])
        );

        $this->assertResponseStatusCodeSame(201);
    }

    /**
     * Les autres routes mutantes de l'API sont protégées elles aussi.
     *
     * L'API n'expose aujourd'hui AUCUNE route PUT/PATCH/DELETE : les seules
     * méthodes mutantes existantes sont des POST. Ces trois verbes figurent
     * bien dans UNSAFE_METHODS du subscriber, mais c'est une garantie
     * prospective — il n'y a rien à protéger avec, pour l'instant.
     */
    public function testLesAutresRoutesMutantesSontProtegees(): void
    {
        $this->seConnecter();

        foreach (['/api/payments', '/api/auth/logout'] as $route) {
            $this->client->request(
                'POST',
                $route,
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                '{}'
            );

            $this->assertSame(
                403,
                $this->client->getResponse()->getStatusCode(),
                sprintf('POST %s sans X-Csrf-Token doit être rejeté en 403.', $route)
            );
        }
    }

    /**
     * Non-régression sur un défaut réel, corrigé le 17/07/2026.
     *
     * POST /api/contact est PUBLIC_ACCESS dans security.yaml — le formulaire est
     * ouvert aux visiteurs non connectés, c'est sa raison d'être. Mais il
     * n'était pas dans EXEMPT_PATHS, alors que le cookie volo_csrf n'est posé
     * qu'au login/register : un visiteur anonyme n'avait donc aucun jeton à
     * envoyer et recevait 403. Le formulaire public était inutilisable pour
     * exactement le public auquel il est destiné.
     *
     * Ce test est écrit sans seConnecter() À DESSEIN : c'est tout son objet.
     * S'il vire au rouge, c'est que quelqu'un a retiré '/api/contact' de
     * EXEMPT_PATHS et recassé le formulaire de contact.
     */
    public function testUnVisiteurAnonymePeutEnvoyerUnMessageDeContact(): void
    {
        // Aucun appel à seConnecter() : on simule un visiteur qui arrive sur
        // le site et remplit le formulaire de contact.
        $this->client->request(
            'POST',
            '/api/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'Sophie',
                'email' => 'visiteuse@example.com',
                'subject' => 'Question sur une commande',
                'message' => 'Bonjour, je souhaite...',
            ])
        );

        $this->assertResponseStatusCodeSame(
            201,
            'Un visiteur anonyme DOIT pouvoir envoyer un message de contact : '
            . '/api/contact est public et aucun jeton CSRF ne peut exister à ce stade.'
        );

        $this->assertNotSame(
            403,
            $this->client->getResponse()->getStatusCode(),
            'Le contrôle CSRF ne doit jamais bloquer le formulaire de contact public.'
        );
    }
}
