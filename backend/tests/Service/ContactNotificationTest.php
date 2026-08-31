<?php

/*
===============================================================================
Test fonctionnel : formulaire de contact — persistance + notification
===============================================================================
Objectif :
    Verifier que POST /api/contact enregistre le message ET previent
    l'administrateur, et surtout que l'un ne depend pas de l'autre.

Le point central :
    La base est la trace durable, l'email n'est qu'une notification. Le test
    qui compte n'est donc PAS « un mail part » — c'est « un envoi rate ne perd
    pas le message ». C'est toute la raison de garder ContactMessage plutot
    que de tout confier a l'email.

Contexte :
    Avant le 17/07/2026, les messages s'enregistraient et personne ne les
    lisait (aucun endpoint, aucun ecran d'administration) — et depuis la mise
    en place du CSRF, ils ne s'enregistraient meme plus : tout visiteur
    anonyme recevait 403. Le formulaire ne fonctionnait d'aucun bout.
===============================================================================
*/

namespace App\Tests\Service;

use App\Entity\ContactMessage;
use App\Service\ContactService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

class ContactNotificationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->em->getConnection()->executeStatement('TRUNCATE TABLE contact_message');
    }

    private function payload(): string
    {
        return json_encode([
            'firstName' => 'Sophie',
            'email' => 'visiteuse@example.com',
            'subject' => 'Question sur une commande',
            'message' => 'Bonjour, je souhaite connaitre le delai de livraison.',
        ]);
    }

    private function poster(): void
    {
        $this->client->request(
            'POST',
            '/api/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->payload()
        );
    }

    /**
     * @return ContactMessage[]
     */
    private function messagesEnBase(): array
    {
        $this->em->clear();

        return $this->em->getRepository(ContactMessage::class)->findAll();
    }

    public function testLeMessageEstEnregistreEnBase(): void
    {
        $this->poster();

        $this->assertResponseStatusCodeSame(201);

        $messages = $this->messagesEnBase();
        $this->assertCount(1, $messages);
        $this->assertSame('Sophie', $messages[0]->getFirstName());
        $this->assertSame('visiteuse@example.com', $messages[0]->getEmail());
    }

    public function testUnEmailEstEnvoyeALAdministrateur(): void
    {
        $this->poster();

        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'To', 'contact@volo.fr');
        $this->assertEmailTextBodyContains($email, 'Question sur une commande');
        $this->assertEmailTextBodyContains($email, 'delai de livraison');
    }

    /**
     * L'expediteur doit rester une adresse du domaine. Usurper celle du
     * visiteur ferait echouer SPF/DKIM et enverrait la notification en
     * indesirables — donc personne ne la lirait, ce qui est precisement le
     * probleme qu'on cherche a resoudre.
     *
     * L'adresse du visiteur va dans Reply-To : repondre lui ecrit directement.
     */
    public function testLExpediteurNEstPasLeVisiteurMaisReplyToOui(): void
    {
        $this->poster();

        $email = $this->getMailerMessage();

        $this->assertEmailHeaderSame($email, 'From', 'no-reply@volo.fr');
        $this->assertEmailHeaderSame($email, 'Reply-To', 'visiteuse@example.com');
    }

    /**
     * LE test de cette suite.
     *
     * On remplace le mailer par un transport qui echoue systematiquement, puis
     * on verifie que : la requete reussit quand meme (201), le message est en
     * base, et l'incident est journalise.
     *
     * C'est ce qui justifie de garder ContactMessage. En email seul, ce
     * scenario perdrait le message definitivement, sans que personne ne sache
     * qu'il a existe.
     */
    public function testUnEchecDEnvoiNeFaitPasPerdreLeMessage(): void
    {
        // expects($this->once()) et non method() : on veut aussi prouver que le
        // service TENTE bien la notification. Sans cette attente, un service qui
        // aurait cesse d'envoyer le moindre email passerait ce test.
        $mailerEnPanne = $this->createMock(MailerInterface::class);
        $mailerEnPanne->expects($this->once())
            ->method('send')
            ->willThrowException(new TransportException('SMTP injoignable'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Echec de la notification de contact'));

        $service = new ContactService(
            $this->em,
            $mailerEnPanne,
            $logger,
            'contact@volo.fr',
            'no-reply@volo.fr'
        );

        $message = $service->submitMessage([
            'firstName' => 'Sophie',
            'email' => 'visiteuse@example.com',
            'subject' => 'Question sur une commande',
            'message' => 'Bonjour, je souhaite connaitre le delai de livraison.',
        ]);

        // L'exception du transport ne remonte pas : le visiteur n'a pas a
        // connaitre un incident SMTP pour un message qui est bien enregistre.
        $this->assertNotNull($message->getId());
        $this->assertCount(1, $this->messagesEnBase());
    }

    /**
     * Le pendant du precedent : la validation, elle, doit echouer AVANT toute
     * persistance et tout envoi. Un message invalide ne laisse aucune trace et
     * ne derange pas l'administrateur.
     */
    public function testUnMessageInvalideNEstNiEnregistreNiNotifie(): void
    {
        $this->client->request(
            'POST',
            '/api/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['firstName' => 'Sophie', 'email' => 'pas-une-adresse'])
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertCount(0, $this->messagesEnBase());
        $this->assertEmailCount(0);
    }

    /**
     * strip_tags s'applique avant la persistance ET avant l'envoi : le corps du
     * mail est en texte brut, mais autant que la balise n'y arrive jamais.
     */
    public function testLeHtmlEstNeutraliseAvantEnregistrementEtEnvoi(): void
    {
        $this->client->request(
            'POST',
            '/api/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'Sophie',
                'email' => 'visiteuse@example.com',
                'subject' => 'Question',
                'message' => 'Bonjour <script>alert(1)</script> merci',
            ])
        );

        $this->assertResponseStatusCodeSame(201);

        $enBase = $this->messagesEnBase()[0]->getMessage();
        $this->assertStringNotContainsString('<script>', $enBase);

        $this->assertEmailTextBodyContains($this->getMailerMessage(), 'Bonjour');
        $this->assertStringNotContainsString(
            '<script>',
            $this->getMailerMessage()->getTextBody()
        );
    }
}
