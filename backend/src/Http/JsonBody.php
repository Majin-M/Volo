<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Lecture des entrees d'API — un seul endroit, une seule regle par type.
 *
 * Le probleme corrige (audit du 14/09/2026) : un test de robustesse de 478
 * requetes malformees a produit 27 erreurs 500, toutes de la meme famille :
 * le code faisait confiance au TYPE des donnees recues.
 *
 *   - `$data = json_decode(...); if (!$data) { 400 }` laisse passer un corps
 *     JSON valide qui n'est pas un objet (`"x"`, `123`) : la valeur arrivait
 *     dans un service type `array $data` -> TypeError.
 *   - `trim($data['email'])` sur un tableau -> TypeError.
 *   - `(int) "12,50"` vaut 12 et `(int) true` vaut 1 : aucune erreur, mais une
 *     commande de 12 unites acceptee pour une saisie invalide.
 *
 * Une faute du client doit rendre 400, jamais 500 : un 500 declenchable par
 * n'importe qui est une surface d'attaque autant qu'un bug.
 */
final class JsonBody
{
    /**
     * Le corps de la requete s'il est un OBJET JSON, null sinon.
     *
     * Corps vide, JSON invalide, scalaire, liste non vide : null. `{}` et `[]`
     * donnent tous deux un tableau vide, indiscernables : a l'appelant d'exiger
     * ses champs.
     *
     * @return array<mixed>|null
     */
    public static function decode(Request $request): ?array
    {
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return null;
        }

        if ($data !== [] && array_is_list($data)) {
            return null;
        }

        return $data;
    }

    /**
     * La valeur si c'est une chaine, null sinon.
     *
     * A appeler avant trim(), strip_tags() ou tout parametre type `string`.
     */
    public static function string(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    /**
     * Entier STRICT : un entier JSON, ou une chaine de 1 a 18 chiffres (signe
     * autorise). Flottant, booleen, "12,50", "2abc", tableau : null.
     *
     * 18 chiffres au plus pour rester sous PHP_INT_MAX sans debordement.
     */
    public static function int(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && preg_match('/^-?\d{1,18}$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
