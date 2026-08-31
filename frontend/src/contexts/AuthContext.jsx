/*
===============================================================================
Context : AuthContext
===============================================================================
Objectif :
    Gerer l'etat d'authentification de l'utilisateur a l'echelle de
    l'application React.

Responsabilites :
    - Restaurer la session au chargement en interrogeant /api/auth/me : le
      token JWT vit dans un cookie HttpOnly, jamais lisible en JavaScript,
      donc c'est la seule facon de savoir si l'utilisateur est deja connecte.
    - Stocker uniquement le profil utilisateur (donnees non sensibles) pour
      l'affichage (NavBar, etc.).
    - Exposer login() et logout() aux composants consommateurs.

Fonctions exposees (via le Provider) :
    - isAuthenticated (bool)
    - user (object|null)
    - isLoading (bool) : true pendant la restauration de session au montage.
    - login(user)  : Met a jour l'etat local apres une connexion reussie
                      (le cookie est deja pose par le backend a ce stade).
    - logout()     : Appelle /api/auth/logout (supprime le cookie cote
                      serveur) puis reinitialise l'etat local.

Exemple d'utilisation :
    const { isAuthenticated, user, login, logout } = useAuth();
===============================================================================
*/

import { createContext, useState, useEffect, useMemo, use } from 'react';
import { apiCall } from '../api/api';

const AuthContext = createContext(null);

const USER_STORAGE_KEY = 'volo_user:v1';

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(() => {
        const stored = localStorage.getItem(USER_STORAGE_KEY);
        return stored ? JSON.parse(stored) : null;
    });
    const [isLoading, setIsLoading] = useState(true);

    // Au montage, on demande au backend qui est authentifie a partir du
    // cookie HttpOnly. Le profil garde en localStorage (ci-dessus) ne sert
    // qu'a eviter un flash "non connecte" pendant cette verification ; seule
    // la reponse de /auth/me fait foi.
    useEffect(() => {
        const restoreSession = async () => {
            try {
                const response = await apiCall('/auth/me');
                setUser(response.data.user);
                localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(response.data.user));
            } catch {
                setUser(null);
                localStorage.removeItem(USER_STORAGE_KEY);
            } finally {
                setIsLoading(false);
            }
        };

        restoreSession();
    }, []);

    /**
     * Met a jour l'etat local apres une connexion ou une inscription reussie.
     * Le cookie HttpOnly a deja ete pose par la reponse du backend a ce stade.
     *
     * @param {object} userData Profil utilisateur renvoye par l'API.
     * @returns {void}
     */
    const login = (userData) => {
        setUser(userData);
        localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(userData));
    };

    /**
     * Deconnecte l'utilisateur : demande au backend de supprimer le cookie,
     * puis reinitialise l'etat local independamment du resultat de l'appel.
     *
     * @returns {Promise<void>}
     */
    const logout = async () => {
        try {
            await apiCall('/auth/logout', { method: 'POST' });
        } catch (err) {
            console.error('Logout error:', err);
        } finally {
            setUser(null);
            localStorage.removeItem(USER_STORAGE_KEY);
        }
    };

    /**
     * Met a jour le profil utilisateur via PATCH /api/auth/me et
     * rafraichit l'etat local avec les donnees renvoyees par le backend.
     *
     * @param {object} fields Champs a modifier (firstName, lastName, currentPassword, newPassword).
     * @returns {Promise<object>} Le profil mis a jour.
     * @throws {Error} Si l'API renvoie une erreur (validation, mot de passe incorrect, etc.).
     */
    const updateProfile = async (fields) => {
        const response = await apiCall('/auth/me', {
            method: 'PATCH',
            body: JSON.stringify(fields),
        });
        const updatedUser = response.data.user;
        setUser(updatedUser);
        localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(updatedUser));
        return updatedUser;
    };

    const value = useMemo(
        () => ({
            isAuthenticated: !!user,
            user,
            isLoading,
            login,
            logout,
            updateProfile,
        }),
        [user, isLoading]
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

// eslint-disable-next-line react-refresh/only-export-components
export const useAuth = () => use(AuthContext);
