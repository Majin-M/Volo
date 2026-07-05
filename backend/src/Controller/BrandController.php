<?php

/*
===============================================================================
Contrôleur : BrandController
===============================================================================
Objectif :
    Exposer la liste des marques disponibles.

Responsabilités :
    - Lister toutes les marques (GET /api/brands).

Routes disponibles :
    - GET /api/brands (Public)
===============================================================================
*/

namespace App\Controller;

use App\Repository\BrandRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class BrandController extends AbstractController
{
    private BrandRepository $brandRepository;

    public function __construct(BrandRepository $brandRepository)
    {
        $this->brandRepository = $brandRepository;
    }

    #[Route('/api/brands', name: 'api_brands_list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        // On récupère toutes les marques triées par nom
        $brands = $this->brandRepository->findAllSortedByName();

        // Note : Pour sérialiser proprement, assure-toi d'avoir ajouté 
        // #[Groups('product:read')] sur les propriétés de l'entité Brand
        return $this->json([
            'data' => $brands
        ], 200, [], ['groups' => 'product:read']);
    }
}