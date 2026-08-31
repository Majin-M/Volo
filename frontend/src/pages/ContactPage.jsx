/*
===============================================================================
Page : ContactPage
===============================================================================
Objectif :
    Permettre a un visiteur d'envoyer un message via le formulaire de contact.

Responsabilites :
    - Afficher les informations pratiques (adresse, email, horaires).
    - Afficher le formulaire de contact et gerer sa soumission.
    - Gerer l'etat du formulaire (champs, statut, erreur) via un useReducer
      unique : ces valeurs changent toujours ensemble (ex. la soumission
      reussie reinitialise les 4 champs ET passe le statut a "success" en
      une seule transition), donc un seul dispatch les decrit de facon
      coherente plutot que plusieurs setters independants.

Dependances :
    - submitContactMessage (Function) : Pour envoyer le message a l'API.

Exemple d'utilisation :
    <Route path="/contact" element={<ContactPage />} />
===============================================================================
*/

import { useReducer } from 'react';
import { Helmet } from 'react-helmet-async';
import { submitContactMessage } from '../api/contactApi';
import { validateEmail, isRequired } from '../utils/validators';
import styles from './ContactPage.module.css';

const initialState = {
    firstName: '',
    email: '',
    subject: '',
    message: '',
    status: 'idle', // 'idle' | 'submitting' | 'success'
    error: null,
};

/**
 * Reducer du formulaire de contact.
 *
 * @param {typeof initialState} state Etat courant du formulaire.
 * @param {{type: string, field?: string, value?: string, error?: string}} action Action a appliquer.
 * @returns {typeof initialState} Nouvel etat.
 */
function contactReducer(state, action) {
    switch (action.type) {
        case 'FIELD_CHANGE':
            return { ...state, [action.field]: action.value };
        case 'SUBMIT_START':
            return { ...state, status: 'submitting', error: null };
        case 'SUBMIT_SUCCESS':
            return { ...initialState, status: 'success' };
        case 'SUBMIT_ERROR':
            return { ...state, status: 'idle', error: action.error };
        default:
            return state;
    }
}

const ContactPage = () => {
    const [state, dispatch] = useReducer(contactReducer, initialState);
    const { firstName, email, subject, message, status, error } = state;
    const loading = status === 'submitting';
    const success = status === 'success';

    /**
     * Valide les champs du formulaire avant tout appel a l'API.
     *
     * @returns {string|null} Le premier message d'erreur rencontre, ou null si le formulaire est valide.
     */
    const validateForm = () => {
        if (!isRequired(firstName) || !isRequired(email) || !isRequired(subject) || !isRequired(message)) {
            return 'Tous les champs sont requis.';
        }
        if (!validateEmail(email)) {
            return "L'adresse email est invalide.";
        }
        return null;
    };

    /**
     * Met a jour un champ du formulaire.
     *
     * @param {string} field Nom du champ ('firstName', 'email', 'subject', 'message').
     * @returns {(e: React.ChangeEvent) => void}
     */
    const handleFieldChange = (field) => (e) => {
        dispatch({ type: 'FIELD_CHANGE', field, value: e.target.value });
    };

    /**
     * Soumet le formulaire de contact a l'API.
     *
     * @param {React.FormEvent} e Evenement de soumission du formulaire.
     * @returns {Promise<void>}
     */
    const handleSubmit = async (e) => {
        e.preventDefault();

        const validationError = validateForm();
        if (validationError) {
            dispatch({ type: 'SUBMIT_ERROR', error: validationError });
            return;
        }

        dispatch({ type: 'SUBMIT_START' });

        try {
            await submitContactMessage({ firstName, email, subject, message });
            dispatch({ type: 'SUBMIT_SUCCESS' });
        } catch (err) {
            console.error('Contact error:', err);
            dispatch({
                type: 'SUBMIT_ERROR',
                error: err.message || "Une erreur est survenue lors de l'envoi. Veuillez reessayer.",
            });
        }
    };

    return (
        <div className={styles.container}>
            <Helmet>
                <title>Contact — VOLO</title>
                <meta
                    name="description"
                    content="Une question sur un produit ou une commande ? Contactez l'equipe VOLO, reponse sous 48h."
                />
            </Helmet>

            <h1 className={styles.pageTitle}>Contactez-nous</h1>
            <p className={styles.pageSubtitle}>
                Une question sur un produit ou une commande ? Notre equipe vous repond sous 48h.
            </p>

            <div className={styles.layout}>
                {/* Informations pratiques */}
                <div className={styles.infoPanel}>
                    <h2 className={styles.infoTitle}>Informations</h2>

                    <div className={styles.infoRow}>
                        <span className={styles.infoIcon}>📍</span>
                        <span>12 rue de la Paix, 75001 Paris</span>
                    </div>
                    <div className={styles.infoRow}>
                        <span className={styles.infoIcon}>✉️</span>
                        <a href="mailto:contact@volo-skin.fr" className={styles.infoLink}>
                            contact@volo-skin.fr
                        </a>
                    </div>
                    <div className={styles.infoRow}>
                        <span className={styles.infoIcon}>🕐</span>
                        <span>Lun-Ven, 9h-18h</span>
                    </div>
                </div>

                {/* Formulaire */}
                <div className={styles.formPanel}>
                    {success ? (
                        <div className={styles.successMsg}>
                            Votre message a bien ete envoye. Nous vous repondrons rapidement.
                        </div>
                    ) : (
                        <form onSubmit={handleSubmit}>
                            {error && <div className={styles.errorMsg}>{error}</div>}

                            <div className={styles.formGroup}>
                                <label className={styles.inputLabel} htmlFor="contact-firstName">Prenom</label>
                                <input
                                    id="contact-firstName"
                                    type="text"
                                    placeholder="Sophie"
                                    value={firstName}
                                    onChange={handleFieldChange('firstName')}
                                    required
                                    className={styles.input}
                                />
                            </div>

                            <div className={styles.formGroup}>
                                <label className={styles.inputLabel} htmlFor="contact-email">Email</label>
                                <input
                                    id="contact-email"
                                    type="email"
                                    placeholder="sophie@example.com"
                                    value={email}
                                    onChange={handleFieldChange('email')}
                                    required
                                    className={styles.input}
                                />
                            </div>

                            <div className={styles.formGroup}>
                                <label className={styles.inputLabel} htmlFor="contact-subject">Sujet</label>
                                <input
                                    id="contact-subject"
                                    type="text"
                                    placeholder="Question sur une commande"
                                    value={subject}
                                    onChange={handleFieldChange('subject')}
                                    required
                                    className={styles.input}
                                />
                            </div>

                            <div className={styles.formGroup}>
                                <label className={styles.inputLabel} htmlFor="contact-message">Message</label>
                                <textarea
                                    id="contact-message"
                                    placeholder="Bonjour, je souhaite..."
                                    value={message}
                                    onChange={handleFieldChange('message')}
                                    required
                                    rows={5}
                                    className={styles.textarea}
                                />
                            </div>

                            <button type="submit" className={styles.primaryButton} disabled={loading}>
                                {loading ? 'Envoi...' : 'Envoyer'}
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
};

export default ContactPage;
