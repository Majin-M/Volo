<?php

/*
===============================================================================
Point d'entree : ApiEntryPoint
===============================================================================
Objectif :
    Uniformiser la reponse envoyee quand une requete non authentifiee atteint
    une route protegee de l'API.

Pourquoi cette classe existe :
    Sans point d'entree explicite, c'est LexikJWTAuthenticationBundle qui
    repond, avec sa propre forme : {"code":401,"message":"JWT Token not
    found"}. Trois defauts : l'enveloppe n'est pas celle du contrat (pas de
    cle "error"), le message est en anglais alors que toute l'API repond en
    francais, et il decrit un detail d'implementation plutot que ce que le
    client doit faire. C'est pourtant l'erreur la plus souvent rencontree,
    puisque tout appel sans session la declenche.

Utilise par :
    - firewall 'api' dans security.yaml (cle entry_point).
===============================================================================
*/

namespace App\Security;

use App\Http\ApiError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class ApiEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return ApiError::response('Authentification requise.', Response::HTTP_UNAUTHORIZED);
    }
}
