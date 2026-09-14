<?php

/*
===============================================================================
Test fonctionnel : bornes de pagination
===============================================================================
Pourquoi ce fichier existe :
    `ProductController` plafonnait `limit` a 50 et s'arretait la. Rien ne
    gardait le plancher, et `ProductService` calcule `($page - 1) * $limit` :
    `?limit=-5` et `?page=0` descendaient donc jusqu'a Doctrine sous forme de
    `setMaxResults(-5)` / `setFirstResult(-20)`.

    `OrderController` avait exactement le meme trou sur `limit` — plafond a
    100, pas de plancher.

Ce qui compte ici :
    Une requete malformee sur une route PUBLIQUE doit rendre un resultat
    par defaut, jamais une erreur serveur. Un 500 declenchable par un
    parametre d'URL est autant une surface d'attaque qu'un bug : il est
    gratuit a produire, et il bavarde en environnement de developpement.

    docs/STRATEGIE_TESTS.md §1 listait le plafond de pagination comme
    « jamais teste, mais le code existe ». Le plafond existait ; le plancher,
    non. C'est ce qu'un test aurait dit tout de suite.
===============================================================================
*/

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PaginationBoundsTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    /**
     * Valeurs NUMERIQUES hors plage : elles doivent etre ramenees dans les
     * bornes et repondre 200. Ce sont celles qui descendaient jusqu'a Doctrine.
     *
     * @return array<string, array{string}>
     */
    public static function parametresHorsPlage(): array
    {
        return [
            'limite negative' => ['/api/products?limit=-5'],
            'limite a zero' => ['/api/products?limit=0'],
            'page a zero' => ['/api/products?page=0'],
            'page negative' => ['/api/products?page=-3'],
            'les deux negatifs' => ['/api/products?page=-1&limit=-10'],
            'limite enorme' => ['/api/products?limit=100000'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('parametresHorsPlage')]
    public function testUneValeurHorsPlageEstRameneeDansLesBornes(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful(sprintf('%s doit repondre 200 apres bornage.', $url));
    }

    /**
     * Une valeur NON NUMERIQUE est un cas different, et la distinction merite
     * d'etre ecrite : elle rend 400, pas 200 — et c'est correct. Symfony
     * refuse de convertir "abc" en entier, et ExceptionSubscriber enveloppe le
     * refus dans le format d'erreur standard du projet.
     *
     * Le contrat que ce fichier defend n'est donc pas « toujours 200 », c'est
     * « JAMAIS une erreur serveur ». Un 400 dit au client que sa requete est
     * fautive ; un 500 lui dirait que le serveur est fautif. Confondre les
     * deux, c'est ce qui transforme un parametre d'URL en surface d'attaque.
     */
    public function testUneValeurNonNumeriqueRendUneErreurClientPasServeur(): void
    {
        $this->client->request('GET', '/api/products?limit=abc');

        self::assertResponseStatusCodeSame(400);

        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('error', $json, 'Le refus doit passer par l\'enveloppe d\'erreur standard.');
        self::assertSame(400, $json['error']['code']);
    }

    public function testLePlafondRameneLaLimiteA50(): void
    {
        $this->client->request('GET', '/api/products?limit=100000');

        self::assertResponseIsSuccessful();

        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('meta', $json, 'La reponse doit exposer la limite effectivement appliquee.');
        self::assertSame(50, $json['meta']['limit']);
    }

    public function testUneLimiteNegativeEstRameneeAuPlancher(): void
    {
        $this->client->request('GET', '/api/products?limit=-5');

        self::assertResponseIsSuccessful();

        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($json);
        self::assertGreaterThanOrEqual(1, $json['meta']['limit'], 'La limite appliquee ne doit jamais etre negative ou nulle.');
    }

    public function testUnePageNulleEstRameneeALaPremiere(): void
    {
        $this->client->request('GET', '/api/products?page=0');

        self::assertResponseIsSuccessful();

        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('meta', $json);
        self::assertSame(1, $json['meta']['page']);
    }
}
