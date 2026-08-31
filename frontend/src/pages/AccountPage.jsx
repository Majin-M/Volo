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

import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { useAuth } from '../contexts/AuthContext';
import styles from './AccountPage.module.css';

const AccountPage = () => {
    const navigate = useNavigate();
    const { user, logout, updateProfile } = useAuth();

    const [isEditing, setIsEditing] = useState(false);
    const [firstName, setFirstName] = useState('');
    const [lastName, setLastName] = useState('');

    const [showPasswordForm, setShowPasswordForm] = useState(false);
    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');

    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState(null);

    if (!user) {
        return (
            <div className={styles.container}>
                <div className={styles.card}>
                    <p style={{ textAlign: 'center', color: '#7a6a60' }}>
                        Vous devez etre connecte pour acceder a cette page.
                    </p>
                    <button
                        type="button"
                        className={styles.primaryButton}
                        onClick={() => navigate('/connexion')}
                    >
                        Se connecter
                    </button>
                </div>
            </div>
        );
    }

    const startEditing = () => {
        setFirstName(user.firstName || '');
        setLastName(user.lastName || '');
        setIsEditing(true);
        setMessage(null);
    };

    const cancelEditing = () => {
        setIsEditing(false);
        setShowPasswordForm(false);
        setCurrentPassword('');
        setNewPassword('');
        setConfirmPassword('');
        setMessage(null);
    };

    const handleSaveProfile = async () => {
        setMessage(null);

        const fields = {};
        if (firstName !== (user.firstName || '')) fields.firstName = firstName;
        if (lastName !== (user.lastName || '')) fields.lastName = lastName;

        if (showPasswordForm) {
            if (!currentPassword) {
                setMessage({ type: 'error', text: 'Le mot de passe actuel est requis.' });
                return;
            }
            if (!newPassword) {
                setMessage({ type: 'error', text: 'Le nouveau mot de passe est requis.' });
                return;
            }
            if (newPassword !== confirmPassword) {
                setMessage({ type: 'error', text: 'Les mots de passe ne correspondent pas.' });
                return;
            }
            fields.currentPassword = currentPassword;
            fields.newPassword = newPassword;
        }

        if (Object.keys(fields).length === 0) {
            setMessage({ type: 'info', text: 'Aucune modification detectee.' });
            return;
        }

        setSaving(true);
        try {
            await updateProfile(fields);
            setMessage({ type: 'success', text: 'Profil mis a jour.' });
            setIsEditing(false);
            setShowPasswordForm(false);
            setCurrentPassword('');
            setNewPassword('');
            setConfirmPassword('');
        } catch (err) {
            setMessage({ type: 'error', text: err.message || 'Erreur lors de la mise a jour.' });
        } finally {
            setSaving(false);
        }
    };

    const handleLogout = async () => {
        await logout();
        navigate('/');
    };

    return (
        <>
            <Helmet>
                <meta name="robots" content="noindex, nofollow" />
                <title>Mon compte — VOLO</title>
            </Helmet>

            <div className={styles.container}>
                <h1 className={styles.title}>Mon compte</h1>

                <div className={styles.card}>
                    <div className={styles.avatarWrapper}>
                        <span className={styles.avatarInitial}>
                            {(user.firstName || user.email || '?')[0].toUpperCase()}
                        </span>
                    </div>

                    {message && (
                        <div className={`${styles.message} ${styles[message.type]}`}>
                            {message.text}
                        </div>
                    )}

                    {!isEditing ? (
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
                                    value={firstName}
                                    onChange={(e) => setFirstName(e.target.value)}
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
                                    value={lastName}
                                    onChange={(e) => setLastName(e.target.value)}
                                    maxLength={255}
                                />
                            </div>

                            <div className={styles.infoRow} style={{ marginTop: '8px' }}>
                                <span className={styles.infoLabel}>Email</span>
                                <span className={styles.infoValue}>{user.email}</span>
                            </div>

                            {!showPasswordForm ? (
                                <button
                                    type="button"
                                    className={styles.linkButton}
                                    onClick={() => setShowPasswordForm(true)}
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
                                            value={currentPassword}
                                            onChange={(e) => setCurrentPassword(e.target.value)}
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
                                            value={newPassword}
                                            onChange={(e) => setNewPassword(e.target.value)}
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
                                            value={confirmPassword}
                                            onChange={(e) => setConfirmPassword(e.target.value)}
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
                                    disabled={saving}
                                >
                                    {saving ? 'Enregistrement...' : 'Enregistrer'}
                                </button>
                                <button
                                    type="button"
                                    className={styles.secondaryButton}
                                    onClick={cancelEditing}
                                    disabled={saving}
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
