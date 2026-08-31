<?php

/*
===============================================================================
Contrôleur : ContactController
===============================================================================
Objectif :
    Exposer l'endpoint du formulaire de contact.

Responsabilites :
    - Recevoir la soumission du formulaire de contact.
    - Deleguer la validation et la creation a ContactService.
    - Formater la reponse JSON.

Routes disponibles :
    - POST /api/contact : Envoyer un message de contact.

Securite :
    Public
===============================================================================
*/

namespace App\Controller;

use App\Service\ContactService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    /**
     * @param ContactService $contactService Valide et persiste le message de contact.
     * @param RateLimiterFactory $contactAttemptsLimiter Limiteur de tentatives de contact.
     */
    public function __construct(
        private ContactService $contactService,
        #[Autowire(service: 'limiter.contact_attempts')] private RateLimiterFactory $contactAttemptsLimiter,
    ) {
    }

    /**
     * Envoie un message via le formulaire de contact.
     *
     * @param Request $request Corps attendu : { firstName, email, subject, message }.
     * @return JsonResponse 201 avec un message de confirmation, ou une erreur 400.
     */
    #[Route('/api/contact', name: 'api_contact_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $limiter = $this->contactAttemptsLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return new JsonResponse(['error' => 'Trop de tentatives. Veuillez reessayer plus tard.'], 429);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Format JSON invalide.'], 400);
        }

        try {
            $this->contactService->submitMessage($data);

            return new JsonResponse([
                'data' => [
                    'message' => 'Votre message a bien ete envoye.',
                ],
            ], JsonResponse::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}
