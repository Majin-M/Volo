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

Dependances :
    - EntityManagerInterface

Used By :
    - ContactController
===============================================================================
*/

namespace App\Service;

use App\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;

class ContactService
{
    private const MAX_SUBJECT_LENGTH = 200;
    private const MAX_MESSAGE_LENGTH = 5000;

    /**
     * @param EntityManagerInterface $entityManager Pour persister le ContactMessage.
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
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

        $this->entityManager->persist($contactMessage);
        $this->entityManager->flush();

        return $contactMessage;
    }
}
