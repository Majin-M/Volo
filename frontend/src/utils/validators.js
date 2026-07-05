/*
===============================================================================
Module : validators
===============================================================================
Objectif :
    Centraliser les regles de validation des formulaires cote frontend.

Responsabilites :
    - Valider le format d'une adresse email.
    - Valider la complexite d'un mot de passe (8 caracteres, chiffre,
      caractere special) — reproduit la meme regle que PasswordValidator.php
      cote backend, pour donner un retour immediat a l'utilisateur.

Note :
    Cette validation cote client est un confort UX, PAS une mesure de
    securite : elle peut toujours etre contournee. La validation qui fait
    foi est celle du backend (PasswordValidator.php, AuthController).

Exemple d'utilisation :
    import { validateEmail, validatePassword } from '../utils/validators';
    const passwordErrors = validatePassword(password);
===============================================================================
*/

const SPECIAL_CHARS_PATTERN = /[!@#$%^&*(),.?":{}|<>_\-+=~`[\]\\/;']/;

/**
 * Verifie le format d'une adresse email.
 *
 * @param {string} email Adresse email a valider.
 * @returns {boolean} True si le format est valide.
 */
export const validateEmail = (email) => {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
};

/**
 * Verifie la complexite d'un mot de passe.
 *
 * @param {string} password Mot de passe en clair a valider.
 * @returns {string[]} Liste des erreurs rencontrees (vide si le mot de passe est valide).
 */
export const validatePassword = (password) => {
    const errors = [];

    if (password.length < 8) {
        errors.push('Le mot de passe doit contenir au moins 8 caracteres.');
    }
    if (!/[0-9]/.test(password)) {
        errors.push('Le mot de passe doit contenir au moins un chiffre.');
    }
    if (!SPECIAL_CHARS_PATTERN.test(password)) {
        errors.push('Le mot de passe doit contenir au moins un caractere special.');
    }

    return errors;
};

/**
 * Verifie qu'un champ requis n'est pas vide (apres suppression des espaces).
 *
 * @param {string} value Valeur du champ.
 * @returns {boolean} True si le champ est renseigne.
 */
export const isRequired = (value) => {
    return typeof value === 'string' && value.trim().length > 0;
};
