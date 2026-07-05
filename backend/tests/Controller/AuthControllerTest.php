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

        // 3. On vide toutes les tables pour repartir à zéro (TRUNCATE)
        // On désactive temporairement la vérification des clés étrangères
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        // On récupère la liste de toutes les tables
        $tables = $connection->createSchemaManager()->listTableNames();

        foreach ($tables as $table) {
            // On ignore les tables de migration ou système si nécessaire,
            // mais ici on veut tout nettoyer pour les tests.
            $connection->executeStatement('TRUNCATE TABLE ' . $table);
        }

        // On réactive la vérification des clés étrangères
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
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

        // Vérifie le contenu du JSON
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        
        // On vérifie que la clé 'data' existe
        $this->assertArrayHasKey('data', $responseContent);
        $this->assertEquals('Sophie', $responseContent['data']['firstName']);
        
        // Vérifie que le mot de passe n'est PAS dans la réponse (sécurité)
        $this->assertArrayNotHasKey('password', $responseContent['data']);
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
        $this->assertStringContainsString('déjà utilisé', strtolower($response['error']));
    }
}