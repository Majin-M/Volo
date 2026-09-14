<?php

/*
===============================================================================
Test fonctionnel : robustesse de l'API face aux entrees malformees
===============================================================================
Pourquoi ce fichier existe :
    Audit du 14/09/2026 : un test de robustesse de 478 requetes malformees
    contre la pile Docker a produit 27 erreurs 500, toutes de la meme famille —
    le code faisait confiance au TYPE des donnees recues :
      - corps JSON valide mais pas un objet (`"x"`, `123`) ;
      - champ attendu en chaine recu en tableau (trim() -> TypeError) ;
      - identifiant d'URL non numerique ou superieur a PHP_INT_MAX ;
      - filtre `?brand=abc` transmis a un parametre `?int` ;
      - valeurs hors des limites de la base (nom > 255 caracteres, prix 1e308,
        stock hors INT), refusees par MySQL.
    Et un defaut silencieux : `(int) "12,50"` acceptait une commande de 12.

Regle figee ici : une faute du client rend 400 (ou 404), JAMAIS 500.

Chaque famille a son CONTROLE POSITIF : sans lui, ces tests passeraient aussi
avec une API qui repondrait 400 a tout.
===============================================================================
*/

namespace App\Tests\Controller;

use App\Entity\Brand;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ApiInputRobustnessTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'Volo-Test-2026!';

    private KernelBrowser $client;
    private int $productId;
    private int $brandId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $connexion = $em->getConnection();
        $connexion->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['audit_log', 'payment', 'order_item', 'shop_order', 'product_skin_concern', 'product', 'brand', 'user', 'contact_message'] as $table) {
            $connexion->executeStatement('TRUNCATE TABLE ' . $table);
        }
        $connexion->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        // Les limiteurs de tentatives comptent par IP et survivent entre tests.
        $container->get('cache.rate_limiter')->clear();

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);
        foreach ([['client@volo.test', ['ROLE_USER']], ['admin@volo.test', ['ROLE_ADMIN']]] as [$email, $roles]) {
            $user = (new User())->setEmail($email)->setFirstName('Test')->setLastName('Robustesse')->setRoles($roles);
            $user->setPassword($hasher->hashPassword($user, self::MOT_DE_PASSE));
            $em->persist($user);
        }

        $brand = (new Brand())->setName('Marque robustesse');
        $product = (new Product())->setName('Serum robustesse')->setPrice('10.00')->setStock(50)->setBrand($brand);
        $em->persist($brand);
        $em->persist($product);
        $em->flush();

        $this->productId = (int) $product->getId();
        $this->brandId = (int) $brand->getId();
    }

    private function connecter(string $email): void
    {
        $this->client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['username' => $email, 'password' => self::MOT_DE_PASSE]));
        self::assertResponseIsSuccessful('La connexion de test doit reussir.');
    }

    /** Requete d'ecriture avec le double envoi CSRF, comme le front. */
    private function envoyer(string $method, string $uri, string $corps): void
    {
        $csrf = $this->client->getCookieJar()->get('volo_csrf')?->getValue() ?? '';
        $this->client->request($method, $uri, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ], $corps);
    }

    private function commande(string $quantite): string
    {
        return sprintf(
            '{"items":[{"productId":%d,"quantity":%s}],"shippingAddress":{"street":"1 rue","city":"Lyon","postalCode":"69001"}}',
            $this->productId,
            $quantite,
        );
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);

        return $data;
    }

    // --- Corps JSON qui n'est pas un objet ---

    /** @return array<string, array{string}> */
    public static function corpsNonObjet(): array
    {
        return ['chaine' => ['"x"'], 'nombre' => ['123'], 'liste' => ['[1,2]']];
    }

    #[DataProvider('corpsNonObjet')]
    public function testUneCommandeDontLeCorpsNestPasUnObjetRend400(string $corps): void
    {
        $this->connecter('client@volo.test');
        $this->envoyer('POST', '/api/orders', $corps);

        self::assertResponseStatusCodeSame(400);
    }

    #[DataProvider('corpsNonObjet')]
    public function testUnMessageDeContactDontLeCorpsNestPasUnObjetRend400(string $corps): void
    {
        $this->client->request('POST', '/api/contact', [], [], ['CONTENT_TYPE' => 'application/json'], $corps);

        self::assertResponseStatusCodeSame(400);
    }

    // --- Champs de type inattendu ---

    public function testUneConnexionAvecDesChampsTableauxRend400(): void
    {
        $this->client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['username' => [], 'password' => ['x']]));

        self::assertResponseStatusCodeSame(400);
    }

    public function testUneInscriptionAvecUnEmailTableauRend400(): void
    {
        $this->client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['email' => ['nouveau@volo.test'], 'password' => self::MOT_DE_PASSE]));

        self::assertResponseStatusCodeSame(400);
    }

    public function testUneInscriptionAvecUnMotDePasseGeantRend400(): void
    {
        // Au-dela de 4096 caracteres, le hacheur leve une exception : 500 avant correction.
        $this->client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['email' => 'geant@volo.test', 'password' => 'Aa1!' . str_repeat('a', 5000)]));

        self::assertResponseStatusCodeSame(400);
    }

    public function testUnPrenomTableauDansLeProfilRend400(): void
    {
        $this->connecter('client@volo.test');
        $this->envoyer('PATCH', '/api/auth/me', (string) json_encode(['firstName' => ['x']]));

        self::assertResponseStatusCodeSame(400);
    }

    public function testUnProfilValideResteModifiable(): void
    {
        $this->connecter('client@volo.test');
        $this->envoyer('PATCH', '/api/auth/me', (string) json_encode(['firstName' => 'Camille']));

        self::assertResponseIsSuccessful('Controle positif : une modification valide doit passer.');
    }

    // --- Identifiants et filtres ---

    /** @return array<string, array{string}> */
    public static function identifiantsImpossibles(): array
    {
        return ['texte' => ['abc'], 'trop grand' => ['99999999999999999999'], 'zero' => ['0'], 'negatif' => ['-1']];
    }

    #[DataProvider('identifiantsImpossibles')]
    public function testUnIdentifiantProduitImpossibleRend404(string $id): void
    {
        $this->client->request('GET', '/api/products/' . $id);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnIdentifiantProduitValideResteAccessible(): void
    {
        $this->client->request('GET', '/api/products/' . $this->productId);

        self::assertResponseIsSuccessful('Controle positif : un identifiant valide doit repondre.');
    }

    /** @return array<string, array{string}> */
    public static function filtresInvalides(): array
    {
        return ['marque texte' => ['brand=abc'], 'marque tableau' => ['brand[]=1'], 'problematique tableau' => ['skin_concern[]=x']];
    }

    #[DataProvider('filtresInvalides')]
    public function testUnFiltreInvalideRendUneListeVide(string $parametres): void
    {
        $this->client->request('GET', '/api/products?' . $parametres);

        self::assertResponseIsSuccessful();
        $json = $this->json();
        self::assertSame([], $json['data']);
    }

    public function testUnFiltreDeMarqueValideFiltreToujours(): void
    {
        $this->client->request('GET', '/api/products?brand=' . $this->brandId);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->json()['data'], 'Controle positif : le filtre valide doit trouver le produit.');
    }

    // --- Ecriture de produits (administrateur) ---

    /** @return array<string, array{array<string, mixed>}> */
    public static function produitsInvalides(): array
    {
        return [
            'nom numerique' => [['name' => 123]],
            'nom de 300 caracteres' => [['name' => str_repeat('a', 300)]],
            'prix demesure' => [['price' => 1e308]],
            'prix texte' => [['price' => 'abc']],
            'stock hors INT' => [['stock' => 99999999999]],
            'stock negatif' => [['stock' => -1]],
            'stock decimal texte' => [['stock' => '12,5']],
            'disponibilite texte' => [['isAvailable' => 'false']],
            'marque texte' => [['brandId' => 'abc']],
        ];
    }

    /** @param array<string, mixed> $surcharge */
    #[DataProvider('produitsInvalides')]
    public function testUnProduitInvalideRend400(array $surcharge): void
    {
        $this->connecter('admin@volo.test');
        $produit = array_merge(['name' => 'Produit', 'price' => '9.90', 'brandId' => $this->brandId], $surcharge);
        $this->envoyer('POST', '/api/products', (string) json_encode($produit));

        self::assertResponseStatusCodeSame(400);
    }

    public function testUnProduitValideEstCree(): void
    {
        $this->connecter('admin@volo.test');
        $this->envoyer('POST', '/api/products', (string) json_encode([
            'name' => 'Creme valide', 'price' => '19.90', 'brandId' => $this->brandId, 'stock' => 12, 'isAvailable' => true,
        ]));

        self::assertResponseStatusCodeSame(201, 'Controle positif : un produit valide doit etre cree.');
    }

    public function testModifierUnProduitAvecUnPrixDemesureRend400(): void
    {
        $this->connecter('admin@volo.test');
        $this->envoyer('PUT', '/api/products/' . $this->productId, (string) json_encode(['price' => 1e308]));

        self::assertResponseStatusCodeSame(400);
    }

    // --- Quantites de commande strictes ---

    /** @return array<string, array{string}> */
    public static function quantitesInvalides(): array
    {
        return ['decimal texte' => ['"12,50"'], 'booleen' => ['true'], 'flottant' => ['2.5'], 'texte mixte' => ['"2abc"']];
    }

    #[DataProvider('quantitesInvalides')]
    public function testUneQuantiteNonEntiereRend400(string $quantite): void
    {
        $this->connecter('client@volo.test');
        $this->envoyer('POST', '/api/orders', $this->commande($quantite));

        self::assertResponseStatusCodeSame(400);
    }

    public function testUneQuantiteEntiereResteAcceptee(): void
    {
        $this->connecter('client@volo.test');
        $this->envoyer('POST', '/api/orders', $this->commande('2'));

        self::assertResponseStatusCodeSame(201, 'Controle positif : une commande valide doit etre acceptee.');
    }
}
