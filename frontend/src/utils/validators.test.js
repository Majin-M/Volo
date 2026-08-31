import { describe, it, expect } from 'vitest';
import { validateEmail, validatePassword, isRequired } from './validators';

describe('validateEmail', () => {
    it('accepte un email valide', () => {
        expect(validateEmail('user@example.com')).toBe(true);
    });

    it('accepte un email avec sous-domaine', () => {
        expect(validateEmail('user@mail.example.com')).toBe(true);
    });

    it('refuse une chaine vide', () => {
        expect(validateEmail('')).toBe(false);
    });

    it('refuse un email sans @', () => {
        expect(validateEmail('userexample.com')).toBe(false);
    });

    it('refuse un email sans domaine', () => {
        expect(validateEmail('user@')).toBe(false);
    });

    it('refuse un email avec espace', () => {
        expect(validateEmail('user @example.com')).toBe(false);
    });
});

describe('validatePassword', () => {
    it('accepte un mot de passe valide (8+ chars, chiffre, special)', () => {
        expect(validatePassword('Passw0rd!')).toEqual([]);
    });

    it('refuse un mot de passe trop court', () => {
        const errors = validatePassword('Ab1!');
        expect(errors).toContain('Le mot de passe doit contenir au moins 8 caracteres.');
    });

    it('refuse un mot de passe sans chiffre', () => {
        const errors = validatePassword('Password!');
        expect(errors).toContain('Le mot de passe doit contenir au moins un chiffre.');
    });

    it('refuse un mot de passe sans caractere special', () => {
        const errors = validatePassword('Password1');
        expect(errors).toContain('Le mot de passe doit contenir au moins un caractere special.');
    });

    it('retourne plusieurs erreurs si plusieurs regles violees', () => {
        const errors = validatePassword('abc');
        expect(errors).toHaveLength(3);
    });
});

describe('isRequired', () => {
    it('accepte une chaine non vide', () => {
        expect(isRequired('hello')).toBe(true);
    });

    it('refuse une chaine vide', () => {
        expect(isRequired('')).toBe(false);
    });

    it('refuse une chaine ne contenant que des espaces', () => {
        expect(isRequired('   ')).toBe(false);
    });

    it('refuse une valeur non string', () => {
        expect(isRequired(null)).toBe(false);
        expect(isRequired(undefined)).toBe(false);
    });
});
