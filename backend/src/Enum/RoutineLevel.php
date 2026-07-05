<?php

/*
===============================================================================
Enum : RoutineLevel
===============================================================================
Objectif :
    Classer les routines de soin selon le niveau de complexité et de connaissance
    requis de l'utilisateur.

Valeurs possibles :
    - BEGINNER     : Routine simple et efficace (Nettoyant + Hydratant).
                     Idéale pour ceux qui débutent la skincare.
    - INTERMEDIATE : Routine structurée intégrant des actifs spécifiques
                     (Sérum, Crème contour des yeux).
    - ADVANCED     : Routine complexe avec plusieurs couches (Layering),
                     utilisant des acides forts ou des rétinoïdes.

Utilisation :
    Permet de filtrer l'affichage des routines dans l'API et sur le Front-end
    pour ne pas submerger l'utilisateur avec des produits inadaptés à son niveau.
===============================================================================
*/

namespace App\Enum;

enum RoutineLevel: string
{
    case BEGINNER = 'beginner';
    case INTERMEDIATE = 'intermediate';
    case ADVANCED = 'advanced';
}