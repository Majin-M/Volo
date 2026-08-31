/*
===============================================================================
Module : Communication API (Wrapper)
===============================================================================
Objectif :
    Centraliser les appels HTTP vers le backend Symfony.

Responsabilites :
    - Construire l'URL complete de l'API.
    - Envoyer les cookies avec chaque requete (credentials: 'include'), pour
      que le cookie HttpOnly contenant le JWT soit transmis automatiquement.
    - Attacher le jeton CSRF (lu depuis le cookie lisible 'volo_csrf') sur
      toute requete mutante (POST, PUT, PATCH, DELETE), conformement au
      controle effectue par CsrfProtectionSubscriber cote backend.
    - Gerer les erreurs HTTP generiques.

Parametres :
    - endpoint (string) : Chemin relatif de l'API (ex: '/products').
    - options (object)  : Options supplementaires pour fetch (method, body...).

Exemple d'utilisation :
    const data = await apiCall('/products');
    await apiCall('/orders', { method: 'POST', body: JSON.stringify(payload) });
===============================================================================
*/

const API_BASE_URL = '/api';
const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

/**
 * Lit la valeur d'un cookie par son nom.
 *
 * @param {string} name Nom du cookie.
 * @returns {string|null} Valeur du cookie, ou null s'il n'existe pas.
 */
function getCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
}

export async function apiCall(endpoint, options = {}) {
    const method = (options.method || 'GET').toUpperCase();

    const headers = {
        'Content-Type': 'application/json',
        ...options.headers,
    };

    if (UNSAFE_METHODS.includes(method)) {
        const csrfToken = getCookie('volo_csrf');
        if (csrfToken) {
            headers['X-Csrf-Token'] = csrfToken;
        }
    }

    const config = {
        ...options,
        headers,
        credentials: 'include',
    };

    const response = await fetch(`${API_BASE_URL}${endpoint}`, config);

    if (!response.ok) {
        let errorMessage = `Erreur API: ${response.statusText}`;

        try {
            const text = await response.text();
            console.error("Corps de la reponse:", text);

            try {
                const errorData = JSON.parse(text);
                if (errorData.error) {
                    if (typeof errorData.error === 'string') {
                        errorMessage = errorData.error;
                    } else if (errorData.error.message) {
                        errorMessage = errorData.error.message;
                    }
                } else if (errorData.message) {
                    errorMessage = errorData.message;
                }
            } catch {
                if (text) {
                    errorMessage = text;
                }
            }
        } catch (readErr) {
            console.error("Impossible de lire le corps de la reponse:", readErr);
        }

        throw new Error(errorMessage);
    }

    if (response.status === 204) {
        return null;
    }

    return response.json();
}
