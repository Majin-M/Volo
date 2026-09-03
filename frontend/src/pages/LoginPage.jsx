/*
===============================================================================
Page : LoginPage
===============================================================================
Objectif :
    Page de connexion, layout split-screen (visuel de marque + formulaire).

Responsabilites :
    - Panneau visuel plein cadre avec citation de marque (masque en mobile).
    - Valider les champs cote client avant tout appel a l'API.
    - Formulaire de connexion avec validation visuelle des champs.
    - Afficher un message clair en cas de blocage temporaire (429) suite a
      un trop grand nombre de tentatives (protection brute force cote API).
    - Redirect vers le panier si succes.

Exemple d'utilisation :
    <Route path="/connexion" element={<LoginPage />} />
===============================================================================
*/

import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { apiCall } from '../api/api';
import { useAuth } from '../contexts/AuthContext';
import { validateEmail, isRequired } from '../utils/validators';
import FormField from '../components/FormField';
import { useToast } from '../contexts/ToastContext';
import styles from './LoginPage.module.css';

const LoginPage = () => {
    const navigate = useNavigate();
    const { login } = useAuth();
    const { addToast } = useToast();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    /**
     * Valide les champs du formulaire avant tout appel a l'API.
     *
     * @returns {string|null} Le premier message d'erreur rencontre, ou null si le formulaire est valide.
     */
    const validateForm = () => {
        if (!isRequired(email) || !isRequired(password)) {
            return 'Email et mot de passe sont requis.';
        }
        if (!validateEmail(email)) {
            return "L'adresse email est invalide.";
        }
        return null;
    };

    const handleLogin = async (e) => {
        e.preventDefault();
        setError(null);

        const validationError = validateForm();
        if (validationError) {
            setError(validationError);
            return;
        }

        setLoading(true);

        try {
            const response = await apiCall('/auth/login', {
                method: 'POST',
                body: JSON.stringify({
                    username: email,
                    password: password
                })
            });

            if (response.data?.user) {
                login(response.data.user);
                addToast('Connexion reussie', 'success');
                navigate('/panier');
            } else {
                setError("Une erreur est survenue.");
            }

        } catch (err) {
            setError(err.message || "Email ou mot de passe incorrect.");
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
        <Helmet>
            <title>Connexion — VOLO</title>
            <meta name="description" content="Connectez-vous a votre compte VOLO pour retrouver votre panier, vos commandes et votre routine skincare personnalisee." />
            <meta name="robots" content="noindex, nofollow" />
        </Helmet>
        <div className={styles.splitScreen}>
            {/* Panneau visuel — masque sur mobile via CSS */}
            <div className={styles.visualPanel}>
                <img
                    src="/images/auth/login-visual.jpg"
                    alt=""
                    className={styles.visualImage}
                />
                <div className={styles.visualOverlay}>
                    <span className={styles.visualEyebrow}>VOLO</span>
                    <h2 className={styles.visualQuote}>
                        La simplicite est l'ultime sophistication.
                    </h2>
                </div>
            </div>

            {/* Panneau formulaire */}
            <div className={styles.formPanel}>
                <div className={styles.formCard}>
                    <span className={styles.eyebrow}>Espace client</span>
                    <h1 className={styles.title}>Bienvenue</h1>
                    <p className={styles.subtitle}>
                        Connectez-vous pour retrouver votre panier et vos commandes.
                    </p>

                    {error && <div className={styles.errorMsg}>{error}</div>}

                    <form onSubmit={handleLogin} noValidate>
                        <FormField
                            label="Email"
                            id="login-email"
                            type="email"
                            placeholder="client@volo.fr"
                            value={email}
                            onChange={setEmail}
                            validate={(v) => {
                                if (!isRequired(v)) return 'L\'email est requis.';
                                if (!validateEmail(v)) return 'Adresse email invalide.';
                                return null;
                            }}
                            required
                            autoComplete="email"
                            className={styles.input}
                        />

                        <FormField
                            label="Mot de passe"
                            id="login-password"
                            type="password"
                            placeholder="••••••••"
                            value={password}
                            onChange={setPassword}
                            validate={(v) => !isRequired(v) ? 'Le mot de passe est requis.' : null}
                            required
                            autoComplete="current-password"
                            className={styles.input}
                        />

                        <button type="submit" className={styles.primaryButton} disabled={loading}>
                            {loading ? 'Connexion...' : 'Se connecter'}
                        </button>
                    </form>

                    <p className={styles.switchText}>
                        Pas encore de compte ?
                        <Link to="/inscription" className={styles.switchLink}>
                            Creer un compte
                        </Link>
                    </p>
                </div>
            </div>
        </div>
        </>
    );
};

export default LoginPage;
