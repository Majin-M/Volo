<?php

/*
===============================================================================
EventSubscriber : SecurityHeadersSubscriber
===============================================================================
Objectif :
    Ajouter systematiquement les en-tetes HTTP de securite sur toutes les
    reponses de l'API, en defense contre le XSS reflechi et le clickjacking.

Responsabilites :
    - Ajouter une Content-Security-Policy restrictive.
    - Empecher le navigateur de deviner un type MIME different (nosniff).
    - Interdire l'affichage du site dans une iframe (clickjacking).
    - Limiter les informations transmises via le Referer.

Pourquoi cela protege contre le XSS reflechi :
    Meme si une entree utilisateur non filtree finissait par etre reinjectee
    dans une page HTML (formulaire mal filtre, template back-office...), la
    Content-Security-Policy empeche le navigateur d'executer un <script>
    injecte de cette maniere, car seules les sources explicitement autorisees
    ci-dessous sont executees.
===============================================================================
*/

namespace App\EventSubscriber;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    /**
     * Ajoute les en-tetes de securite a chaque reponse HTTP sortante.
     *
     * @param ResponseEvent $event Evenement de reponse Symfony.
     * @return void
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none';"
        );
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
