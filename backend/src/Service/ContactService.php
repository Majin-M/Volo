<?php

/*
===============================================================================
Service : ContactService
===============================================================================
Objectif :
    Centraliser la logique metier liee au formulaire de contact.

Responsabilites :
    - Valider les donnees soumises par le formulaire de contact.
    - Neutraliser les balises HTML/script dans les champs texte avant stockage.
    - Creer et persister un ContactMessage.
    - Notifier l'administrateur par email qu'un message est arrive.

Persister ET notifier — pourquoi les deux :
    La base est la TRACE DURABLE, l'email n'est qu'une NOTIFICATION.

    Un email n'est pas un stockage : l'envoi peut echouer (SMTP indisponible,
    quota, DNS) ou le message finir en indesirables. Si l'email etait le seul
    support, ces incidents feraient perdre le message DEFINITIVEMENT, sans que
    personne ne sache qu'il a existe. Avec la base, un envoi rate n'est qu'un
    desagrement : la donnee est la, on la relit.

    Reciproquement, la base seule ne suffit pas : c'est ce qui a produit la
    situation d'origine, ou les messages s'empilaient sans que personne ne les
    lise jamais (aucun endpoint, aucun ecran d'administration). L'email est ce
    qui les fait arriver a un humain.

    Consequence sur le modele : ContactMessage devient une ARCHIVE, pas un
    outil de travail. L'administrateur traite dans sa boite mail. C'est ce qui
    rend RG12 et processed_by_user_id inutiles (docs/MODELE_DONNEES.md 6.5).

Dependances :
    - EntityManagerInterface, MailerInterface, LoggerInterface

Used By :
    - ContactController
===============================================================================
*/

namespace App\Service;

use App\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ContactService
{
    private const MAX_SUBJECT_LENGTH = 200;
    private const MAX_MESSAGE_LENGTH = 5000;

    /**
     * @param EntityManagerInterface $entityManager Pour persister le ContactMessage.
     * @param MailerInterface $mailer Pour notifier l'administrateur.
     * @param LoggerInterface $logger Pour tracer un echec d'envoi (le message reste en base).
     * @param string $adminEmail Destinataire de la notification.
     * @param string $expediteur Adresse d'expedition (jamais celle du visiteur, cf. notifierAdministrateur).
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%env(ADMIN_EMAIL)%')] private string $adminEmail,
        #[Autowire('%env(MAILER_FROM)%')] private string $expediteur,
    ) {
    }

    /**
     * Valide les donnees recues, neutralise les balises HTML et cree un ContactMessage.
     *
     * @param array $data Donnees JSON decodees (firstName, email, subject, message).
     * @return ContactMessage Message de contact persiste.
     * @throws \InvalidArgumentException Si un champ requis est manquant ou invalide.
     */
    public function submitMessage(array $data): ContactMessage
    {
        // strip_tags neutralise toute balise <script> ou HTML injectee : le
        // message est stocke en texte pur, ce qui empeche un XSS reflechi ou
        // stocke si ce contenu est un jour reaffiche (ex. back-office EasyAdmin).
        $firstName = strip_tags(trim($data['firstName'] ?? ''));
        $email = trim($data['email'] ?? '');
        $subject = strip_tags(trim($data['subject'] ?? ''));
        $message = strip_tags(trim($data['message'] ?? ''));

        if ($firstName === '' || $email === '' || $subject === '' || $message === '') {
            throw new \InvalidArgumentException('Tous les champs sont requis.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('L\'adresse email est invalide.');
        }

        if (mb_strlen($subject) > self::MAX_SUBJECT_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Le sujet ne doit pas depasser %d caracteres.', self::MAX_SUBJECT_LENGTH)
            );
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Le message ne doit pas depasser %d caracteres.', self::MAX_MESSAGE_LENGTH)
            );
        }

        $contactMessage = new ContactMessage();
        $contactMessage->setFirstName($firstName);
        $contactMessage->setEmail($email);
        $contactMessage->setSubject($subject);
        $contactMessage->setMessage($message);

        // La persistance d'abord, la notification ensuite : dans cet ordre, un
        // echec d'envoi ne peut pas faire perdre le message.
        $this->entityManager->persist($contactMessage);
        $this->entityManager->flush();

        $this->notifierAdministrateur($contactMessage, $email);

        return $contactMessage;
    }

    /**
     * Notifie l'administrateur. Best-effort : un echec est journalise, jamais
     * propage.
     *
     * Pourquoi ne pas laisser l'exception remonter : le message est DEJA en
     * base a ce stade. Rendre une erreur au visiteur lui dirait que son envoi a
     * echoue alors qu'il est bien enregistre — il reessaierait, et on
     * creerait des doublons pour un incident qui ne le concerne pas. Le seul
     * impact reel d'un envoi rate est que l'administrateur n'est pas prevenu
     * tout de suite ; la donnee, elle, est intacte.
     *
     * D'ou l'importance du log en niveau error : sans lui, l'incident serait
     * invisible, et on retomberait dans la panne silencieuse.
     *
     * @param ContactMessage $contactMessage Message deja persiste.
     * @param string $emailVisiteur Adresse du visiteur, deja validee.
     *     Passee a part plutot que relue via $contactMessage->getEmail() : ce
     *     getter renvoie ?string (la propriete de l'entite est nullable), alors
     *     que Email::replyTo() exige un string. Utiliser la valeur validee est
     *     exact au niveau des types, sans cast ni verification redondante.
     * @return void
     */
    private function notifierAdministrateur(ContactMessage $contactMessage, string $emailVisiteur): void
    {
        try {
            $email = (new Email())
                // L'expediteur est TOUJOURS une adresse du domaine, jamais
                // celle du visiteur : usurper son domaine ferait echouer les
                // controles SPF/DKIM et classerait la notification en
                // indesirables. L'adresse du visiteur va dans Reply-To, ce qui
                // permet quand meme de lui repondre d'un clic.
                ->from($this->expediteur)
                ->to($this->adminEmail)
                ->replyTo($emailVisiteur)
                ->subject(sprintf('[VOLO] Contact : %s', $contactMessage->getSubject()))
                ->text($this->corpsDuMessage($contactMessage));

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Echec de la notification de contact — le message reste en base.', [
                'contactMessageId' => $contactMessage->getId(),
                'erreur' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Corps en texte brut : le contenu vient d'un formulaire public. Le rendre
     * en HTML rouvrirait la porte que strip_tags vient de fermer, cette fois
     * dans la boite mail de l'administrateur.
     *
     * @param ContactMessage $contactMessage Message a formater.
     * @return string
     */
    private function corpsDuMessage(ContactMessage $contactMessage): string
    {
        return sprintf(
            "Nouveau message depuis le formulaire de contact VOLO.\n\n"
            . "De      : %s <%s>\n"
            . "Sujet   : %s\n"
            . "Recu le : %s\n\n"
            . "--------------------------------------------------\n%s\n"
            . "--------------------------------------------------\n\n"
            . "Repondre a cet email ecrit directement au visiteur.\n"
            . "Archive en base : contact_message #%d",
            $contactMessage->getFirstName(),
            $contactMessage->getEmail(),
            $contactMessage->getSubject(),
            $contactMessage->getCreatedAt()->format('d/m/Y H:i'),
            $contactMessage->getMessage(),
            $contactMessage->getId()
        );
    }
}
