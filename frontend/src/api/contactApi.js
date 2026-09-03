/*
===============================================================================
Module API : Contact
===============================================================================
Objectif :
    Fournir la methode d'envoi du formulaire de contact vers l'API Symfony.

Responsabilites :
    - Envoyer les donnees du formulaire de contact.
    - Deballer la reponse API pour retourner uniquement les donnees utiles.

Exemple d'utilisation :
    const result = await submitContactMessage({ firstName, email, subject, message });
    // result.data.message -> Confirmation
===============================================================================
*/

import { apiCall } from './api';

/**
 * Envoie un message via le formulaire de contact.
 *
 * @param {{firstName: string, email: string, subject: string, message: string}} payload
 * @returns {Promise<{data: {message: string}}>}
 */
export const submitContactMessage = async (payload) => {
    const data = await apiCall('/contact', {
        method: 'POST',
        body: JSON.stringify(payload)
    });

    return data;
};
