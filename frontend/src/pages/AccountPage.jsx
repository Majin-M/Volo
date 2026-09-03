/*
===============================================================================
Page : AccountPage (Mon compte)
===============================================================================
Objectif :
    Afficher et permettre la modification des informations du compte
    utilisateur connecte.

Responsabilites :
    - Rediriger vers la page de connexion si l'utilisateur n'est pas
      authentifie.
    - Afficher le prenom, le nom et l'email de l'utilisateur.
    - Permettre la modification du prenom et du nom (PATCH /api/auth/me).
    - Permettre le changement de mot de passe (mot de passe actuel requis).
    - Proposer un acces rapide a l'historique des commandes.
    - Permettre la deconnexion via le contexte AuthContext.

Dependances :
    - useAuth (Hook) : Pour recuperer l'utilisateur courant, logout et
      updateProfile.
    - react-router-dom : Pour la navigation (useNavigate).

Exemple d'utilisation :
    <Route path="/mon-compte" element={<AccountPage />} />
===============================================================================
*/

import { useReducer } from 'react';
import { useNavigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { useAuth } from '../contexts/AuthContext';
import styles from './AccountPage.module.css';

const initialState = {
    isEditing: false,
    firstName: '',
    lastName: '',
    showPasswordForm: false,
    currentPassword: '',
    newPassword: '',
    confirmPassword: '',
    saving: false,
    message: null,
};

function reducer(state, action) {
    switch (action.type) {
        case 'START_EDITING':
            return { ...initialState, isEditing: true, firstName: action.firstName, lastName: action.lastName };
        case 'CANCEL':
            return initialState;
        case 'SET_FIELD':
            return { ...state, [action.field]: action.value };
        case 'SHOW_PASSWORD_FORM':
            return { ...state, showPasswordForm: true };
        case 'SAVE_START':
            return { ...state, saving: true, message: null };
        case 'SAVE_SUCCESS':
            return { ...initialState, message: { type: 'success', text: action.text } };
        case 'SAVE_ERROR':
            return { ...state, saving: false, message: { type: 'error', text: action.text } };
        case 'SET_MESSAGE':
            return { ...state, message: action.message };
        default:
            return state;
    }
}

const AccountPage = () => {
    const navigate = useNavigate();
    const { user, logout, updateProfile } = useAuth();
    const [state, dispatch] = useReducer(reducer, initialState);

    const startEditing = () => {
        dispatch({ type: 'START_EDITING', firstName: user.firstName || '', lastName: user.lastName || '' });
    };

    const cancelEditing = () => {
        dispatch({ type: 'CANCEL' });
    };

    const handleSaveProfile = async () => {
        const fields = {};
        if (state.firstName !== (user.firstName || '')) fields.firstName = state.firstName;
        if (state.lastName !== (user.lastName || '')) fields.lastName = state.lastName;

        if (state.showPasswordForm) {
            if (!state.currentPassword) {
                dispatch({ type: 'SET_MESSAGE', message: { type: 'error', text: 'Le mot de passe actuel est requis.' } });
                return;
            }
            if (!state.newPassword) {
                dispatch({ type: 'SET_MESSAGE', message: { type: 'error', text: 'Le nouveau mot de passe est requis.' } });
                return;
            }
            if (state.newPassword !== state.confirmPassword) {
                dispatch({ type: 'SET_MESSAGE', message: { type: 'error', text: 'Les mots de passe ne correspondent pas.' } });
                return;
            }
            fields.currentPassword = state.currentPassword;
            fields.newPassword = state.newPassword;
        }

        if (Object.keys(fields).length === 0) {
            dispatch({ type: 'SET_MESSAGE', message: { type: 'info', text: 'Aucune modification detectee.' } });
            return;
        }

        dispatch({ type: 'SAVE_START' });
        try {
            await updateProfile(fields);
            dispatch({ type: 'SAVE_SUCCESS', text: 'Profil mis a jour.' });
        } catch (err) {
            dispatch({ type: 'SAVE_ERROR', text: err.message || 'Erreur lors de la mise a jour.' });
        }
    };

    const handleLogout = async () => {
        await logout();
        navigate('/');
    };

    return (
        <>
            <Helmet>
                <title>Mon compte — VOLO</title>
                <meta name="description" content="Gerez votre profil, modifiez vos informations et consultez vos commandes VOLO." />
                <meta name="robots" content="noindex, nofollow" />
            </Helmet>

            <div className={styles.container}>
                <h1 className={styles.title}>Mon compte</h1>

                <div className={styles.card}>
                    <div className={styles.avatarWrapper}>
                        <span className={styles.avatarInitial}>
                            {(user.firstName || user.email || '?')[0].toUpperCase()}
                        </span>
                    </div>

                    {state.message && (
                        <div className={`${styles.message} ${styles[state.message.type]}`}>
                            {state.message.text}
                        </div>
                    )}

                    {!state.isEditing ? (
                        <>
                            <div className={styles.infoGrid}>
                                {user.firstName && (
                                    <div className={styles.infoRow}>
                                        <span className={styles.infoLabel}>Prenom</span>
                                        <span className={styles.infoValue}>{user.firstName}</span>
                                    </div>
                                )}
                                {user.lastName && (
                                    <div className={styles.infoRow}>
                                        <span className={styles.infoLabel}>Nom</span>
                                        <span className={styles.infoValue}>{user.lastName}</span>
                                    </div>
                                )}
                                <div className={styles.infoRow}>
                                    <span className={styles.infoLabel}>Email</span>
                                    <span className={styles.infoValue}>{user.email}</span>
                                </div>
                            </div>

                            <div className={styles.actions}>
                                <button
                                    type="button"
                                    className={styles.secondaryButton}
                                    onClick={startEditing}
                                >
                                    Modifier mon profil
                                </button>
                                <button
                                    type="button"
                                    className={styles.primaryButton}
                                    onClick={() => navigate('/mes-commandes')}
                                >
                                    Mes commandes
                                </button>
                                <button
                                    type="button"
                                    className={styles.dangerButton}
                                    onClick={handleLogout}
                                >
                                    Se deconnecter
                                </button>
                            </div>
                        </>
                    ) : (
                        <>
                            <div className={styles.formGroup}>
                                <label className={styles.formLabel} htmlFor="edit-firstName">
                                    Prenom
                                </label>
                                <input
                                    id="edit-firstName"
                                    type="text"
                                    className={styles.formInput}
                                    value={state.firstName}
                                    onChange={(e) => dispatch({ type: 'SET_FIELD', field: 'firstName', value: e.target.value })}
                                    maxLength={255}
                                />
                            </div>

                            <div className={styles.formGroup}>
                                <label className={styles.formLabel} htmlFor="edit-lastName">
                                    Nom
                                </label>
                                <input
                                    id="edit-lastName"
                                    type="text"
                                    className={styles.formInput}
                                    value={state.lastName}
                                    onChange={(e) => dispatch({ type: 'SET_FIELD', field: 'lastName', value: e.target.value })}
                                    maxLength={255}
                                />
                            </div>

                            <div className={styles.infoRow} style={{ marginTop: '8px' }}>
                                <span className={styles.infoLabel}>Email</span>
                                <span className={styles.infoValue}>{user.email}</span>
                            </div>

                            {!state.showPasswordForm ? (
                                <button
                                    type="button"
                                    className={styles.linkButton}
                                    onClick={() => dispatch({ type: 'SHOW_PASSWORD_FORM' })}
                                >
                                    Changer le mot de passe
                                </button>
                            ) : (
                                <div className={styles.passwordSection}>
                                    <div className={styles.formGroup}>
                                        <label className={styles.formLabel} htmlFor="edit-currentPassword">
                                            Mot de passe actuel
                                        </label>
                                        <input
                                            id="edit-currentPassword"
                                            type="password"
                                            className={styles.formInput}
                                            value={state.currentPassword}
                                            onChange={(e) => dispatch({ type: 'SET_FIELD', field: 'currentPassword', value: e.target.value })}
                                            autoComplete="current-password"
                                        />
                                    </div>
                                    <div className={styles.formGroup}>
                                        <label className={styles.formLabel} htmlFor="edit-newPassword">
                                            Nouveau mot de passe
                                        </label>
                                        <input
                                            id="edit-newPassword"
                                            type="password"
                                            className={styles.formInput}
                                            value={state.newPassword}
                                            onChange={(e) => dispatch({ type: 'SET_FIELD', field: 'newPassword', value: e.target.value })}
                                            autoComplete="new-password"
                                        />
                                    </div>
                                    <div className={styles.formGroup}>
                                        <label className={styles.formLabel} htmlFor="edit-confirmPassword">
                                            Confirmer le mot de passe
                                        </label>
                                        <input
                                            id="edit-confirmPassword"
                                            type="password"
                                            className={styles.formInput}
                                            value={state.confirmPassword}
                                            onChange={(e) => dispatch({ type: 'SET_FIELD', field: 'confirmPassword', value: e.target.value })}
                                            autoComplete="new-password"
                                        />
                                    </div>
                                </div>
                            )}

                            <div className={styles.actions}>
                                <button
                                    type="button"
                                    className={styles.primaryButton}
                                    onClick={handleSaveProfile}
                                    disabled={state.saving}
                                >
                                    {state.saving ? 'Enregistrement...' : 'Enregistrer'}
                                </button>
                                <button
                                    type="button"
                                    className={styles.secondaryButton}
                                    onClick={cancelEditing}
                                    disabled={state.saving}
                                >
                                    Annuler
                                </button>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </>
    );
};

export default AccountPage;
