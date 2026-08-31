<?php

/*
===============================================================================
Test Fonctionnel : AuthControllerTest
===============================================================================
Objectif :
    Tester les points d'entrée d'authentification de l'API.

Cas de test couverts :
    - POST /api/auth/register : Création valide d'un utilisateur.
    - POST /api/auth/register : Erreur si email déjà utilisé.
    - POST /api/auth/register : Erreur si données manquantes.

Environnement :
    Utilise le client de test Symfony (WebTestCase) pour simuler
    des requêtes HTTP réelles sans serveur web.
===============================================================================
*/

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Repository\UserRepository;

class AuthControllerTest extends WebTestCase
{
    /**
     * IP que le client de test présente à l'application. C'est la clé sous
     * laquelle le rate limiter compte les tentatives.
     */
    private const CLIENT_IP = '127.0.0.1';

    /**
     * Tables que le TRUNCATE de setUp() doit épargner.
     *
     * doctrine_migration_versions n'est pas une table métier : c'est l'historique
     * des migrations appliquées. La vider fait croire à Doctrine que la base
     * n'a jamais été migrée, et le prochain doctrine:migrations:migrate tente
     * de tout rejouer sur un schéma qui existe déjà.
     */
    private const TABLES_PRESERVEES = ['doctrine_migration_versions'];

    private $client;

       protected function setUp(): void
    {
        // 1. On initialise le client et le noyau Symfony
        parent::setUp();
        $this->client = static::createClient();

        // 2. On récupère la connexion à la BDD
        $container = static::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $connection = $entityManager->getConnection();

        // 3. On vide les tables métier pour repartir à zéro (TRUNCATE)
        // On désactive temporairement la vérification des clés étrangères
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        // On récupère la liste de toutes les tables
        $tables = $connection->createSchemaManager()->listTableNames();

        foreach ($tables as $table) {
            if (in_array($table, self::TABLES_PRESERVEES, true)) {
                continue;
            }
            $connection->executeStatement('TRUNCATE TABLE ' . $table);
        }

        // On réactive la vérification des clés étrangères
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        // 4. On remet les compteurs de rate limiting à zéro.
        //
        // Sans ça, la suite passe une fois puis tombe en 429 : register_attempts
        // autorise 5 tentatives par heure et ce quota est partagé entre toutes
        // les exécutions, le client de test sortant toujours de la même IP.
        // Un test qui n'est vert qu'une fois par heure ne prouve rien.
        //
        // On réinitialise plutôt que de désactiver le limiteur en env test :
        // le neutraliser rendrait impossible de tester le 429 lui-même, qui est
        // justement un comportement à couvrir (STRATEGIE_TESTS.md §5).
        foreach (['limiter.login_attempts', 'limiter.register_attempts'] as $limiterId) {
            $container->get($limiterId)->create(self::CLIENT_IP)->reset();
        }
    }
    public function testRegister_Success(): void
    {
        // 1. Préparation des données JSON
        $payload = [
            'email' => 'unique_user@volo.fr',
            'password' => 'Password123!',
            'firstName' => 'Sophie',
            'lastName' => 'Martin'
        ];

        // 2. Envoi de la requête POST
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'], // On précise qu'on envoie du JSON
            json_encode($payload)
        );

        // 3. Assertions (Vérifications)

        // Vérifie que le code réponse est 201 Created
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);

        // Vérifie que la réponse est bien du JSON (pas du HTML)
        $this->assertResponseHeaderSame('content-type', 'application/json');

        // Vérifie le contenu du JSON.
        //
        // La forme attendue est celle de docs/api_specification.md §2 : l'objet
        // utilisateur est imbriqué sous 'data.user', pas posé à plat sur 'data'.
        // C'est le contrat publié qui fait référence ici, pas ce que le
        // contrôleur renvoie : une assertion recopiée depuis l'implémentation ne
        // peut par construction jamais la contredire.
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $responseContent);
        $this->assertArrayHasKey('user', $responseContent['data']);

        $user = $responseContent['data']['user'];

        $this->assertSame('Sophie', $user['firstName']);
        $this->assertSame('Martin', $user['lastName']);
        $this->assertSame('unique_user@volo.fr', $user['email']);
        $this->assertIsInt($user['id']);

        // Sécurité : le mot de passe, même haché, ne doit apparaître nulle part.
        //
        // On vérifie sur l'objet 'user' ET sur la charge utile brute. Chercher
        // 'password' à la racine de 'data' ne prouvait rien : la clé n'y a
        // jamais été, donc l'assertion passait sans rien couvrir.
        $this->assertArrayNotHasKey('password', $user);
        $this->assertStringNotContainsString(
            'password',
            $this->client->getResponse()->getContent(),
            'La réponse d\'inscription ne doit exposer aucun champ de mot de passe.'
        );

        // Le jeton ne doit PAS être dans le corps : c'est tout l'objet du choix
        // documenté dans docs/CONTRAT_API.md §1. api_specification.md §2 montre
        // encore un champ 'token' dans la réponse — cet exemple est périmé, et
        // le suivre reviendrait à rendre le jeton lisible par le JavaScript.
        $this->assertArrayNotHasKey('token', $responseContent['data']);

        $cookies = [];
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie;
        }

        // volo_token : porte le JWT, doit être hors de portée du JavaScript.
        // C'est la propriété qui protège le jeton en cas de XSS réussie —
        // STRATEGIE_TESTS.md §1 la classait « jamais testée ».
        $this->assertArrayHasKey('volo_token', $cookies, 'Le cookie JWT doit être posé à l\'inscription.');
        $this->assertTrue($cookies['volo_token']->isHttpOnly(), 'volo_token DOIT être HttpOnly.');
        $this->assertNotEmpty($cookies['volo_token']->getValue());

        // volo_csrf : doit au contraire être lisible par le JS de VOLO, qui le
        // recopie dans l'en-tête X-Csrf-Token (double-submit, CONTRAT_API §2).
        // HttpOnly ici casserait la protection CSRF au lieu de la renforcer.
        $this->assertArrayHasKey('volo_csrf', $cookies, 'Le cookie CSRF doit être posé à l\'inscription.');
        $this->assertFalse($cookies['volo_csrf']->isHttpOnly(), 'volo_csrf NE DOIT PAS être HttpOnly.');
    }

    public function testRegister_InvalidData_MissingEmail(): void
    {
        $payload = [
            'password' => 'Password123!',
            'firstName' => 'Test'
        ];

        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        // Doit retourner une erreur 400 Bad Request
        $this->assertResponseStatusCodeSame(400);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }

        public function testRegister_DuplicateEmail(): void
    {
        // 1. Récupération des services nécessaires (EntityManager et PasswordHasher)
        // On utilise le conteneur de services de Symfony pour interagir avec la BDD
        $container = static::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $passwordHasher = $container->get('security.user_password_hasher');

        // 2. Création manuelle d'un utilisateur déjà existant en base
        $existingUser = new \App\Entity\User();
        $existingUser->setEmail('already_used@volo.fr');
        // Il faut hasher le mot de passe manuellement car on bypasse le contrôleur ici
        $existingUser->setPassword($passwordHasher->hashPassword($existingUser, 'password123'));
        $existingUser->setFirstName('Existait');
        $existingUser->setLastName('Déjà');

        $entityManager->persist($existingUser);
        $entityManager->flush();

        // 3. Tentative d'inscription avec le MÊME email
        $payload = [
            'email' => 'already_used@volo.fr', // Conflit volontaire
            'password' => 'NouveauMdp123!',
            'firstName' => 'Jean',
            'lastName' => 'Dupont'
        ];

        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        // 4. Assertions : On attend une erreur (400 ou 409)
        $this->assertResponseStatusCodeSame(400);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        // On vérifie que la structure d'erreur est présente
        $this->assertArrayHasKey('error', $response);

        // mb_strtolower et non strtolower : strtolower() est octet par octet et
        // ne connaît pas l'UTF-8. Il laisserait 'É' intact tout en abaissant le
        // reste, et la comparaison échouerait sur un message pourtant correct.
        $this->assertStringContainsString('déjà utilisé', mb_strtolower($response['error'], 'UTF-8'));
    }
}