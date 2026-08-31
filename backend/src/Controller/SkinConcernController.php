<?php

/*
===============================================================================
Contrôleur : SkinConcernController
===============================================================================
Objectif :
    Exposer la liste des problématiques de peau.

Responsabilités :
    - Lister toutes les problématiques (GET /api/skin-concerns).

Routes disponibles :
    - GET /api/skin-concerns (Public)
===============================================================================
*/

namespace App\Controller;

use App\Repository\SkinConcernRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SkinConcernController extends AbstractController
{
    private SkinConcernRepository $skinConcernRepository;

    public function __construct(SkinConcernRepository $skinConcernRepository)
    {
        $this->skinConcernRepository = $skinConcernRepository;
    }

    /**
     * Retourne la liste de toutes les problematiques de peau.
     *
     * @return JsonResponse Liste des problematiques serializees (groupe product:read).
     */
    #[Route('/api/skin-concerns', name: 'api_skin_concerns_list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $concerns = $this->skinConcernRepository->findAll();

        return $this->json([
            'data' => $concerns
        ], 200, [], ['groups' => 'product:read']);
    }
}