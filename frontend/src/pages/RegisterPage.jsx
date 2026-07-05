/*
===============================================================================
Page : RegisterPage
===============================================================================
Objectif :
    Page d'inscription, layout split-screen (visuel de marque + formulaire).

Responsabilites :
    - Panneau visuel plein cadre avec citation de marque (masque en mobile).
    - Formulaire multi-champs (Prenom, Nom, Email, Mot de passe, Confirmation).
    - Valider tous les champs cote client avant tout appel a l'API (format
      email, complexite du mot de passe, correspondance des deux mots de
      passe) — en plus, jamais a la place, de la validation serveur.
    - Auto-login via AuthContext apres inscription reussie (token renvoye
      par POST /api/auth/register).

Exemple d'utilisation :
    <Route path="/inscription" element={<RegisterPage />} />
===============================================================================
*/

import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { apiCall } from '../api/api';
import { useAuth } from '../contexts/AuthContext';
import { validateEmail, validatePassword, isRequired } from '../utils/validators';
import styles from './RegisterPage.module.css';

const RegisterPage = () => {
    const navigate = useNavigate();
    const { login } = useAuth();

    const [firstName, setFirstName] = useState('');
    const [lastName, setLastName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    /**
     * Valide l'ensemble des champs du formulaire avant tout appel a l'API.
     *
     * @returns {string|null} Le premier message d'erreur rencontre, ou null si le formulaire est valide.
     */
    const validateForm = () => {
        if (!isRequired(firstName) || !isRequired(lastName) || !isRequired(email) || !isRequired(password)) {
            return 'Tous les champs sont requis.';
        }
        if (!validateEmail(email)) {
            return "L'adresse email est invalide.";
        }
        const passwordErrors = validatePassword(password);
        if (passwordErrors.length > 0) {
            return passwordErrors[0];
        }
        if (password !== confirmPassword) {
            return 'Les deux mots de passe ne correspondent pas.';
        }
        return null;
    };

    const handleRegister = async (e) => {
        e.preventDefault();
        setError(null);

        const validationError = validateForm();
        if (validationError) {
            setError(validationError);
            return;
        }

        setLoading(true);

        try {
            const response = await apiCall('/auth/register', {
                method: 'POST',
                body: JSON.stringify({ firstName, lastName, email, password })
            });

            if (response.data?.user) {
                login(response.data.user);
                navigate('/'); // Auto-login
            } else {
                navigate('/connexion');
            }

        } catch (err) {
            setError(err.message || "Une erreur est survenue.");
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className={styles.splitScreen}>
            {/* Panneau visuel — masque sur mobile via CSS */}
            <div className={styles.visualPanel}>
                <img
                    src="/images/auth/register-visual.jpg"
                    alt=""
                    className={styles.visualImage}
                />
                <div className={styles.visualOverlay}>
                    <span className={styles.visualEyebrow}>VOLO</span>
                    <h2 className={styles.visualQuote}>
                        Votre peau, votre rituel.
                    </h2>
                </div>
            </div>

            {/* Panneau formulaire */}
            <div className={styles.formPanel}>
                <div className={styles.formCard}>
                    <span className={styles.eyebrow}>Nouveau compte</span>
                    <h1 className={styles.title}>Creer mon compte</h1>
                    <p className={styles.subtitle}>
                        Rejoignez VOLO pour suivre vos commandes et retrouver votre routine.
                    </p>

                    {error && <div className={styles.errorMsg}>{error}</div>}

                    <form onSubmit={handleRegister} noValidate>
                        <div className={styles.nameRow}>
                            <div className={styles.formGroup}>
                                <label className={styles.inputLabel} htmlFor="register-firstName">Prenom</label>
                                <input
                                    id="register-firstName"
                                    type="text"
                                    placeholder="Sophie"
                                    value={firstName}
                                    onChange={(e) => setFirstName(e.target.value)}
                                    required
                                    className={styles.input}
                                />
                            </div>

                            <div className={styles.formGroup}>
                                <label className={styles.inputLabel} htmlFor="register-lastName">Nom</label>
                                <input
                                    id="register-lastName"
                                    type="text"
                                    placeholder="Martin"
                                    value={lastName}
                                    onChange={(e) => setLastName(e.target.value)}
                                    required
                                    className={styles.input}
                                />
                            </div>
                        </div>

                        <div className={styles.formGroup}>
                            <label className={styles.inputLabel} htmlFor="register-email">Email</label>
                            <input
                                id="register-email"
                                type="email"
                                placeholder="sophie@example.com"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                required
                                className={styles.input}
                            />
                        </div>

                        <div className={styles.formGroup}>
                            <label className={styles.inputLabel} htmlFor="register-password">Mot de passe</label>
                            <input
                                id="register-password"
                                type="password"
                                placeholder="8 caracteres min., 1 chiffre, 1 caractere special"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                required
                                minLength={8}
                                className={styles.input}
                            />
                        </div>

                        <div className={styles.formGroup}>
                            <label className={styles.inputLabel} htmlFor="register-confirmPassword">Confirmer le mot de passe</label>
                            <input
                                id="register-confirmPassword"
                                type="password"
                                placeholder="••••••••"
                                value={confirmPassword}
                                onChange={(e) => setConfirmPassword(e.target.value)}
                                required
                                minLength={8}
                                className={styles.input}
                            />
                        </div>

                        <button type="submit" className={styles.createButton} disabled={loading}>
                            {loading ? 'Creation...' : "S'inscrire"}
                        </button>
                    </form>

                    <p className={styles.switchText}>
                        Vous avez deja un compte ?
                        <Link to="/connexion" className={styles.switchLink}>
                            Se connecter
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
};

export default RegisterPage;
