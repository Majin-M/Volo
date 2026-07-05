<?php

/*
===============================================================================
Service : PasswordValidator
===============================================================================
Objectif :
    Valider la complexite d'un mot de passe selon la politique de securite
    du site.

Responsabilites :
    - Verifier la longueur minimale (8 caracteres).
    - Verifier la presence d'au moins un chiffre.
    - Verifier la presence d'au moins un caractere special.

Used By :
    - AuthController (inscription)

Note :
    Cette validation est deliberement dupliquee cote frontend (utils/validators.js)
    pour le confort utilisateur, mais SEULE cette validation serveur fait foi :
    un formulaire cote client peut toujours etre contourne (devtools, appel
    direct a l'API), donc ne jamais faire confiance uniquement au frontend.
===============================================================================
*/

namespace App\Service;

class PasswordValidator
{
    private const MIN_LENGTH = 8;
    private const SPECIAL_CHARS_PATTERN = '/[!@#$%^&*(),.?":{}|<>_\-+=~`\[\]\\\\\/;\']/';

    /**
     * Valide un mot de passe selon la politique de securite.
     *
     * @param string $password Mot de passe en clair a valider.
     * @return string[] Liste des erreurs rencontrees (vide si le mot de passe est valide).
     */
    public function validate(string $password): array
    {
        $errors = [];

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $errors[] = sprintf('Le mot de passe doit contenir au moins %d caracteres.', self::MIN_LENGTH);
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
        }

        if (!preg_match(self::SPECIAL_CHARS_PATTERN, $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un caractere special.';
        }

        return $errors;
    }

    /**
     * @param string $password Mot de passe en clair a valider.
     * @return bool True si le mot de passe respecte la politique de securite.
     */
    public function isValid(string $password): bool
    {
        return empty($this->validate($password));
    }
}
