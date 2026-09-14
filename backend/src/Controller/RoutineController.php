<?php

/*
===============================================================================
Contrôleur : RoutineController
===============================================================================
Objectif :
    Exposer en lecture les routines de soin et leurs produits.

Routes disponibles :
    - GET /api/routines (Public)

Paramètres de requête :
    ?level={beginner|intermediate|advanced}  Niveau de la routine
    ?skin_concern={slug}                     Problématique de peau

Note sur le filtrage par problématique :
    Une routine n'est pas liée directement à une problématique. Le lien passe
    par ses produits : RoutineRepository::findByFilters() joint
    routine -> produits -> problématiques. Filtrer sur « acne » retourne donc
    les routines contenant au moins un produit qui cible l'acné.

Dépendances :
    - RoutineRepository
===============================================================================
*/

namespace App\Controller;

use App\Enum\RoutineLevel;
use App\Http\ApiError;
use App\Repository\RoutineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class RoutineController extends AbstractController
{
    public function __construct(private RoutineRepository $routineRepository)
    {
    }

    #[Route('/api/routines', name: 'api_routines_list', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $level = $request->query->get('level');
        $skinConcern = $request->query->get('skin_concern');

        // Un niveau inconnu doit se dire, pas se traduire par une liste vide
        // que le client interpreterait comme « aucune routine disponible ».
        if (\is_string($level) && $level !== '' && RoutineLevel::tryFrom($level) === null) {
            $valides = implode(', ', array_column(RoutineLevel::cases(), 'value'));

            return ApiError::response(
                sprintf('Niveau "%s" inconnu. Valeurs acceptees : %s.', $level, $valides),
                400,
            );
        }

        $routines = $this->routineRepository->findByFilters(
            \is_string($level) && $level !== '' ? $level : null,
            \is_string($skinConcern) && $skinConcern !== '' ? $skinConcern : null,
        );

        return $this->json(
            ['data' => $routines],
            200,
            [],
            // Les deux groupes : 'routine:read' pour la routine, 'product:read'
            // pour les produits imbriques, sans quoi ils sortiraient vides.
            ['groups' => ['routine:read', 'product:read']],
        );
    }
}
