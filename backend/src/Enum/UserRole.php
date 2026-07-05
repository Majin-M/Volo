<?php

/*
===============================================================================
Enum : UserRole
===============================================================================
Objectif :
    Définir la liste fermée des rôles disponibles pour les utilisateurs
    au sein de l'application.

Valeurs possibles :
    - ROLE_USER  : Accès client standard (panier, commande, profil).
    - ROLE_ADMIN : Accès administrateur (gestion produits, commandes, users).

Utilisation :
    Utilisé pour la sécurité Symfony (Voters) et la séparation des droits
    d'accès dans les contrôleurs.
===============================================================================
*/

namespace App\Enum;

enum UserRole: string
{
    case USER = 'ROLE_USER';
    case ADMIN = 'ROLE_ADMIN';
}