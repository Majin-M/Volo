<?php

/*
===============================================================================
EventSubscriber : CsrfProtectionSubscriber
===============================================================================
Objectif :
    Proteger l'API contre le CSRF, rendu necessaire par le passage du JWT
    dans un cookie HttpOnly : le navigateur envoie ce cookie automatiquement
    avec toute requete vers l'API, y compris celles declenchees par un site
    tiers malveillant. Sans ce controle, un site externe pourrait forcer un
    utilisateur connecte a passer une commande ou modifier son compte a son
    insu.

Principe (double-submit cookie) :
    - Un second cookie ('volo_csrf'), NON HttpOnly celui-la, contient un
      jeton aleatoire. Etant lisible en JavaScript, le frontend peut le
      relire et le renvoyer dans un en-tete personnalise ('X-Csrf-Token').
    - Un site tiers peut faire envoyer le cookie automatiquement par le
      navigateur, mais ne peut PAS lire sa valeur (politique de meme
      origine) pour la reproduire dans l'en-tete. Sans en-tete valide
      correspondant au cookie, la requete est rejetee.

Responsabilites :
    - Verifier, pour toute requete /api/* utilisant une methode non sure
      (POST, PUT, PATCH, DELETE), que l'en-tete X-Csrf-Token correspond
      au cookie volo_csrf.
    - Exempter /api/auth/login et /api/auth/register, ou aucun cookie CSRF
      n'existe encore au moment de l'appel.

Configuration requise :
    - AuthController doit poser le cookie 'volo_csrf' au login/register
      (voir buildCsrfCookie()).
===============================================================================
*/

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CsrfProtectionSubscriber implements EventSubscriberInterface
{
    private const COOKIE_NAME = 'volo_csrf';
    private const HEADER_NAME = 'X-Csrf-Token';
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private const EXEMPT_PATHS = ['/api/auth/login', '/api/auth/register'];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    /**
     * Verifie le jeton CSRF sur les requetes API mutantes.
     *
     * @param RequestEvent $event Evenement de requete Symfony.
     * @return void
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        if (!in_array($request->getMethod(), self::UNSAFE_METHODS, true)) {
            return;
        }

        if (in_array($path, self::EXEMPT_PATHS, true)) {
            return;
        }

        $cookieToken = $request->cookies->get(self::COOKIE_NAME);
        $headerToken = $request->headers->get(self::HEADER_NAME);

        if (!$cookieToken || !$headerToken || !hash_equals($cookieToken, $headerToken)) {
            $event->setResponse(new JsonResponse(['error' => 'Jeton CSRF invalide ou manquant.'], 403));
        }
    }
}
