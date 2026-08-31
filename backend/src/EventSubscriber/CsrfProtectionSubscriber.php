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
    - Exempter les routes appelables sans cookie CSRF preexistant :
      /api/auth/login, /api/auth/register et /api/contact (voir EXEMPT_PATHS).

Ordre d'execution :
    Ce subscriber ecoute kernel.request sans priorite, donc a 0. Deux
    ecouteurs Symfony passent avant lui :

        RouterListener (32)  ->  route inconnue   = 404, jamais 403
        Firewall (8)         ->  non authentifie  = 401, jamais 403
        ce subscriber (0)                         = 403

    Le controle CSRF arrive donc en dernier. Ce n'est pas un defaut : une
    route inexistante ne fait rien, et une requete non authentifiee est deja
    rejetee. Mais c'est a savoir pour ne pas s'etonner de recevoir 404 ou 401
    la ou on attendait 403.

Configuration requise :
    - AuthController doit poser le cookie 'volo_csrf' au login/register
      (voir buildCsrfCookie()).

Verification :
    tests/Security/CsrfProtectionTest.php — 8 tests. Eprouves : desactiver la
    comparaison ci-dessous fait tomber 4 d'entre eux.
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
    /**
     * Chemins exemptes du controle CSRF.
     *
     * Le point commun des trois : ils sont appeles par des visiteurs qui n'ont
     * pas encore de cookie 'volo_csrf', puisque seuls login et register en
     * posent un. Les soumettre au controle les rendrait inatteignables.
     *
     * '/api/contact' a ete ajoute le 17/07/2026 : la route est PUBLIC_ACCESS
     * dans security.yaml, mais elle etait soumise au controle CSRF — donc tout
     * visiteur anonyme recevait 403 en envoyant le formulaire de contact. Le
     * formulaire public etait inutilisable pour le public.
     *
     * Pourquoi l'exemption ne coute rien ici : le CSRF protege contre l'usage
     * des identifiants d'une victime CONNECTEE, en exploitant le fait que le
     * navigateur envoie son cookie automatiquement. '/api/contact' n'agit au
     * nom de personne et n'exige aucune authentification : faire soumettre un
     * message par un tiers a son insu n'a aucun benefice pour un attaquant.
     * Ce qui protege cette route est le rate limiter, et un CAPTCHA le jour ou
     * le spam deviendrait reel (cf. docs/TECHNOLOGIES.md section 3).
     */
    private const EXEMPT_PATHS = ['/api/auth/login', '/api/auth/register', '/api/contact'];

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
