<?php

/*
===============================================================================
Fabrique : ApiError
===============================================================================
Objectif :
    Construire l'unique enveloppe d'erreur JSON de l'API.

Pourquoi cette classe existe :
    L'enveloppe { "error": { "code": N, "message": "..." } } etait produite par
    ExceptionSubscriber, mais une quarantaine de retours d'erreur ecrits
    directement dans les controleurs renvoyaient la forme plate
    { "error": "message" }. Deux formes coexistaient donc pour la meme chose,
    selon qu'une exception avait ete levee ou non. Centraliser la construction
    ici est ce qui empeche l'ecart de se reformer : il n'y a plus qu'un seul
    endroit ou la forme est decidee.

Utilisation :
    return ApiError::response('Commande introuvable.', 404);

Voir aussi :
    - ExceptionSubscriber : applique la meme enveloppe aux exceptions.
    - docs/CONTRAT_API.md §6 : le contrat que cette classe fait respecter.
===============================================================================
*/

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiError
{
    /**
     * @param string $message Message destine au client, en francais.
     * @param int    $status  Code HTTP, repris tel quel dans le corps.
     */
    public static function response(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $status,
                'message' => $message,
            ],
        ], $status);
    }
}
