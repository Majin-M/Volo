<?php

/*
===============================================================================
Contrôleur : AuthController
===============================================================================
Objectif :
    Gerer l'authentification des utilisateurs (Login, Register, Logout, Me).

Responsabilites :
    - Enregistrer un nouvel utilisateur et poser son Token JWT dans un cookie
      HttpOnly (Register), accompagne d'un cookie CSRF lisible.
    - Authentifier un utilisateur et poser le JWT + le cookie CSRF (Login).
    - Invalider la session en supprimant les deux cookies (Logout).
    - Retourner l'utilisateur actuellement authentifie a partir du cookie (Me).
    - Limiter le nombre de tentatives par adresse IP pour empecher le
      passage en force (brute force) sur la connexion et l'inscription.
    - Journaliser les echecs de connexion (email tente, IP) pour permettre
      la detection d'attaques, sans jamais journaliser le mot de passe.

Pourquoi deux cookies distincts :
    - 'volo_token' (HttpOnly) : jamais lisible en JavaScript, transporte le JWT.
    - 'volo_csrf' (lisible) : permet au frontend de prouver qu'il n'est pas
      un site tiers (voir CsrfProtectionSubscriber pour le detail du controle).

Routes disponibles :
    - POST   /api/auth/register (Public, limite en tentatives)
    - POST   /api/auth/login    (Public, limite en tentatives)
    - POST   /api/auth/logout   (Protege : ROLE_USER)
    - GET    /api/auth/me       (Protege : ROLE_USER)
    - PATCH  /api/auth/me       (Protege : ROLE_USER, limite en tentatives)

Dependances :
    - UserRepository, UserPasswordHasherInterface, JWTTokenManagerInterface
    - PasswordValidator : Pour verifier la complexite du mot de passe.
    - RateLimiterFactory (limiter.login_attempts, limiter.register_attempts,
      limiter.profile_update_attempts)
    - LoggerInterface : Pour journaliser les echecs de connexion.

Configuration requise :
    - config/packages/lexik_jwt_authentication.yaml doit extraire le token
      depuis le cookie 'volo_token'.
    - CsrfProtectionSubscriber doit etre enregistre (autoconfigure suffit).
===============================================================================
*/

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PasswordValidator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class AuthController extends AbstractController
{
    private const AUTH_COOKIE_NAME = 'volo_token';
    private const CSRF_COOKIE_NAME = 'volo_csrf';
    private const COOKIE_TTL = 3600; // Doit correspondre a token_ttl dans lexik_jwt_authentication.yaml

    /**
     * @param UserRepository $userRepository Pour verifier l'existence de l'email.
     * @param UserPasswordHasherInterface $passwordHasher Pour hasher le mot de passe.
     * @param JWTTokenManagerInterface $jwtManager Pour generer le token JWT.
     * @param EntityManagerInterface $entityManager Pour persister le nouvel utilisateur.
     * @param PasswordValidator $passwordValidator Pour verifier la complexite du mot de passe.
     * @param RateLimiterFactory $loginAttemptsLimiter Limiteur de tentatives de connexion (limiter.login_attempts).
     * @param RateLimiterFactory $registerAttemptsLimiter Limiteur de tentatives d'inscription (limiter.register_attempts).
     * @param LoggerInterface $logger Pour journaliser les echecs de connexion.
     * @param string $environment Environnement courant (kernel.environment), pour activer Secure en production.
     */
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $entityManager,
        private PasswordValidator $passwordValidator,
        #[Autowire(service: 'limiter.login_attempts')] private RateLimiterFactory $loginAttemptsLimiter,
        #[Autowire(service: 'limiter.register_attempts')] private RateLimiterFactory $registerAttemptsLimiter,
        #[Autowire(service: 'limiter.profile_update_attempts')] private RateLimiterFactory $profileUpdateLimiter,
        private LoggerInterface $logger,
        #[Autowire(param: 'kernel.environment')] private string $environment,
    ) {
    }

    /**
     * Construit le cookie HttpOnly contenant le JWT.
     *
     * @param string $token Token JWT signe.
     * @return Cookie
     */
    private function buildAuthCookie(string $token): Cookie
    {
        return Cookie::create(self::AUTH_COOKIE_NAME)
            ->withValue($token)
            ->withHttpOnly(true)
            ->withSecure($this->environment === 'prod')
            ->withSameSite(Cookie::SAMESITE_LAX)
            ->withPath('/')
            ->withExpires(time() + self::COOKIE_TTL);
    }

    /**
     * Construit le cookie CSRF, lisible en JavaScript par design (voir
     * CsrfProtectionSubscriber pour le fonctionnement du controle).
     *
     * @return Cookie
     */
    private function buildCsrfCookie(): Cookie
    {
        return Cookie::create(self::CSRF_COOKIE_NAME)
            ->withValue(bin2hex(random_bytes(32)))
            ->withHttpOnly(false)
            ->withSecure($this->environment === 'prod')
            ->withSameSite(Cookie::SAMESITE_LAX)
            ->withPath('/')
            ->withExpires(time() + self::COOKIE_TTL);
    }

    /**
     * Inscription d'un nouvel utilisateur.
     * Route: POST /api/auth/register
     *
     * @param Request $request Corps attendu : { email, password, firstName, lastName }.
     * @return JsonResponse 201 avec user, ou une erreur 400/429.
     */
    #[Route('/api/auth/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $limiter = $this->registerAttemptsLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return new JsonResponse(['error' => 'Trop de tentatives. Veuillez réessayer plus tard.'], 429);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return new JsonResponse(['error' => 'Email et mot de passe requis.'], 400);
        }

        $email = trim($data['email']);
        $password = $data['password'];
        $firstName = strip_tags(trim($data['firstName'] ?? ''));
        $lastName = strip_tags(trim($data['lastName'] ?? ''));

        if (mb_strlen($firstName) > 255) {
            return new JsonResponse(['error' => 'Le prenom est trop long (max 255 caracteres).'], 400);
        }

        if (mb_strlen($lastName) > 255) {
            return new JsonResponse(['error' => 'Le nom est trop long (max 255 caracteres).'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'L\'adresse email est invalide.'], 400);
        }

        $passwordErrors = $this->passwordValidator->validate($password);
        if (!empty($passwordErrors)) {
            return new JsonResponse(['error' => implode(' ', $passwordErrors)], 400);
        }

        if ($this->userRepository->findOneBy(['email' => $email])) {
            return new JsonResponse(['error' => 'Cet email est déjà utilisé.'], 400);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setFirstName($firstName);
        $user->setLastName($lastName);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

        $response = new JsonResponse([
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                ]
            ]
        ], JsonResponse::HTTP_CREATED);

        $response->headers->setCookie($this->buildAuthCookie($token));
        $response->headers->setCookie($this->buildCsrfCookie());

        return $response;
    }

    /**
     * Connexion : pose le JWT et le cookie CSRF.
     * Route: POST /api/auth/login
     *
     * @param Request $request Corps attendu : { email, password }.
     * @return JsonResponse 200 avec user, ou une erreur 401/429.
     */
    #[Route('/api/auth/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $limiter = $this->loginAttemptsLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return new JsonResponse(['error' => 'Trop de tentatives. Veuillez réessayer dans quelques minutes.'], 429);
        }

        $data = json_decode($request->getContent(), true);
        $email = trim($data['email'] ?? $data['username'] ?? '');
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return new JsonResponse(['error' => 'Email et mot de passe requis.'], 400);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            // On journalise l'email tente et l'IP pour detecter d'eventuelles
            // attaques, mais JAMAIS le mot de passe fourni.
            $this->logger->warning('Echec de connexion', [
                'email' => $email,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'Identifiants invalides.'], 401);
        }

        $token = $this->jwtManager->create($user);

        $response = new JsonResponse([
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'role' => $user->getRoles(),
                ]
            ]
        ]);

        $response->headers->setCookie($this->buildAuthCookie($token));
        $response->headers->setCookie($this->buildCsrfCookie());

        return $response;
    }

    /**
     * Deconnexion : supprime les cookies d'authentification et CSRF.
     * Route: POST /api/auth/logout
     *
     * @return JsonResponse 204 sans corps.
     */
    #[Route('/api/auth/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $response = new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);

        $response->headers->clearCookie(
            self::AUTH_COOKIE_NAME,
            '/',
            null,
            $this->environment === 'prod',
            true,
            Cookie::SAMESITE_LAX
        );
        $response->headers->clearCookie(
            self::CSRF_COOKIE_NAME,
            '/',
            null,
            $this->environment === 'prod',
            false,
            Cookie::SAMESITE_LAX
        );

        return $response;
    }

    /**
     * Retourne l'utilisateur actuellement authentifie (a partir du cookie).
     * Route: GET /api/auth/me
     *
     * @return JsonResponse 200 avec user, ou 401 si non authentifie.
     */
    #[Route('/api/auth/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié.'], 401);
        }

        return new JsonResponse([
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'role' => $user->getRoles(),
                ]
            ]
        ]);
    }

    /**
     * Met a jour le profil de l'utilisateur connecte.
     * Route: PATCH /api/auth/me
     *
     * Champs modifiables : firstName, lastName, password (avec currentPassword).
     *
     * @param Request $request Corps JSON avec les champs a modifier.
     * @return JsonResponse 200 avec le profil mis a jour.
     */
    #[Route('/api/auth/me', name: 'api_me_update', methods: ['PATCH'])]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié.'], 401);
        }

        // Rate limiting : empeche les modifications de profil trop frequentes
        $limiter = $this->profileUpdateLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return new JsonResponse(['error' => 'Trop de tentatives. Veuillez reessayer plus tard.'], 429);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Format JSON invalide.'], 400);
        }

        // Mise a jour du prenom
        if (isset($data['firstName'])) {
            $firstName = strip_tags(trim($data['firstName']));
            if ($firstName === '') {
                return new JsonResponse(['error' => 'Le prénom ne peut pas être vide.'], 400);
            }
            if (mb_strlen($firstName) > 255) {
                return new JsonResponse(['error' => 'Le prenom est trop long (max 255 caracteres).'], 400);
            }
            $user->setFirstName($firstName);
        }

        // Mise a jour du nom
        if (isset($data['lastName'])) {
            $lastName = strip_tags(trim($data['lastName']));
            if ($lastName === '') {
                return new JsonResponse(['error' => 'Le nom ne peut pas être vide.'], 400);
            }
            if (mb_strlen($lastName) > 255) {
                return new JsonResponse(['error' => 'Le nom est trop long (max 255 caracteres).'], 400);
            }
            $user->setLastName($lastName);
        }

        // Changement de mot de passe (necessite le mot de passe actuel)
        if (isset($data['newPassword'])) {
            if (empty($data['currentPassword'])) {
                return new JsonResponse(['error' => 'Le mot de passe actuel est requis pour en définir un nouveau.'], 400);
            }

            if (!$this->passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
                return new JsonResponse(['error' => 'Mot de passe actuel incorrect.'], 400);
            }

            $passwordErrors = $this->passwordValidator->validate($data['newPassword']);
            if (!empty($passwordErrors)) {
                return new JsonResponse(['error' => implode(' ', $passwordErrors)], 400);
            }

            $user->setPassword($this->passwordHasher->hashPassword($user, $data['newPassword']));
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'role' => $user->getRoles(),
                ]
            ]
        ]);
    }
}
