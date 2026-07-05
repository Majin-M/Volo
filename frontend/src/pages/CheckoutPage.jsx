/*
===============================================================================
Page : CheckoutPage (Validation de commande)
===============================================================================
Objectif :
    Page de paiement et de validation de commande.

Responsabilites :
    - Presenter le formulaire d'adresse et le formulaire Stripe proprement.
    - Creer la commande (POST /orders) puis initialiser le paiement (POST /payments).
    - Gerer l'etat de chargement (Spinner).
    - Afficher le total de maniere elegante.
    - Empecher l'indexation de cette page par les moteurs de recherche
      (page privee, sans interet SEO).

Dependances :
    - useCart (Hook) : Pour acceder a cartItems, cartTotal.
    - apiCall (Function) : Pour envoyer la commande puis initier le paiement.
    - PaymentForm (Composant) : Formulaire Stripe.

Exemple d'utilisation :
    <Route path="/commande" element={<CheckoutPage />} />
===============================================================================
*/

import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { useCart } from '../contexts/CartContext';
import { apiCall } from '../api/api';
import PaymentForm from '../components/PaymentForm';
import styles from './CheckoutPage.module.css';

// JSX statique (ne depend d'aucune prop/etat) : construit une seule fois.
const noindexTag = (
    <Helmet>
        <meta name="robots" content="noindex, nofollow" />
    </Helmet>
);

const CheckoutPage = () => {
    const navigate = useNavigate();
    const { cartItems, cartTotal, clearCart } = useCart();

    // Etats
    const [shippingAddress, setShippingAddress] = useState({
        street: '',
        city: '',
        postalCode: '',
        country: 'France' // Valeur par defaut
    });

    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [clientSecret, setClientSecret] = useState(null);
    const [paymentSuccess, setPaymentSuccess] = useState(false);

    // Gestion changement adresse
    const handleChange = (e) => {
        const { name, value } = e.target;
        setShippingAddress(prev => ({ ...prev, [name]: value }));
    };

    /**
     * 1. Creation de la commande (Backend)
     * 2. Initialisation du paiement Stripe avec l'orderId obtenu
     */
    const handlePlaceOrder = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        // 1. Check Token
        const token = localStorage.getItem('token');
        if (!token) {
            setError("Vous devez etre connecte pour commander.");
            setLoading(false);
            return;
        }

        try {
            // Transformation Items
            const itemsPayload = cartItems.map(item => ({
                productId: item.id,
                quantity: item.quantity
            }));

            // --- ETAPE 1 : Creer la commande ---
            const orderResponse = await apiCall('/orders', {
                method: 'POST',
                body: JSON.stringify({
                    items: itemsPayload,
                    shippingAddress: shippingAddress
                })
            });

            const orderId = orderResponse.data.id;

            if (!orderId) {
                throw new Error("Impossible de recuperer l'identifiant de la commande.");
            }

            // --- ETAPE 2 : Initialiser le paiement avec l'orderId ---
            const paymentResponse = await apiCall('/payments', {
                method: 'POST',
                body: JSON.stringify({ orderId })
            });

            setClientSecret(paymentResponse.data.clientSecret);

        } catch (err) {
            console.error(err);
            setError("Erreur lors de l'initialisation du paiement.");
        } finally {
            setLoading(false);
        }
    };

    /**
     * Callback apres succes du paiement
     */
    const handlePaymentSuccess = (paymentIntent) => {
        setPaymentSuccess(true);
        alert(`Commande n°${paymentIntent.id} validee avec succes !`);
        clearCart();
        navigate('/'); // Redirection Accueil
    };

    // Si panier vide -> Retour accueil
    if (cartItems.length === 0) {
        return (
            <>
                {noindexTag}
                <div style={{ textAlign: 'center', marginTop: '50px', color: '#5F4C42' }}>
                    <h2>Votre panier est vide.</h2>
                    <button
                        type="button"
                        className={styles.primaryButton}
                        style={{ width: 'auto', padding: '15px 40px' }}
                        onClick={() => navigate('/')}
                    >
                        Retourner au catalogue
                    </button>
                </div>
            </>
        );
    }

    return (
        <>
            {noindexTag}
            <div className={styles.container}>
                <div className={styles.checkoutCard}>
                    <div className={styles.totalStyle}>
                        <span>Total</span>
                        <span>{cartTotal.toFixed(2)} €</span>
                    </div>

                    {error && <div className={styles.errorMsg}>{error}</div>}

                    {/* Grille Adresse | Paiement */}
                    <div className={styles.formGrid}>

                        {/* GAUCHE : Formulaire Adresse */}
                        <form onSubmit={handlePlaceOrder}>
                            <h2 className={styles.sectionTitle}>Livraison</h2>

                            <div className={styles.formGroup}>
                                <label className={styles.sectionLabel} htmlFor="checkout-street">Rue et N°</label>
                                <input
                                    id="checkout-street"
                                    type="text"
                                    name="street"
                                    placeholder="10 avenue des Champs"
                                    value={shippingAddress.street}
                                    onChange={handleChange}
                                    required
                                    className={styles.inputField}
                                />
                            </div>

                            <div style={{ display: 'flex', gap: '20px' }}>
                                <div style={{ flex: 1 }}>
                                    <label className={styles.sectionLabel} htmlFor="checkout-city">Ville</label>
                                    <input
                                        id="checkout-city"
                                        type="text"
                                        name="city"
                                        placeholder="Paris"
                                        value={shippingAddress.city}
                                        onChange={handleChange}
                                        required
                                        className={styles.inputField}
                                    />
                                </div>
                                <div style={{ flex: 1 }}>
                                    <label className={styles.sectionLabel} htmlFor="checkout-postal">Code Postal</label>
                                    <input
                                        id="checkout-postal"
                                        type="text"
                                        name="postalCode"
                                        placeholder="75001"
                                        value={shippingAddress.postalCode}
                                        onChange={handleChange}
                                        required
                                        className={styles.inputField}
                                    />
                                </div>
                            </div>

                            <button type="submit" className={styles.primaryButton} disabled={loading || !!clientSecret}>
                                {loading ? 'Traitement...' : clientSecret ? 'Adresse validee' : 'Continuer vers le paiement'}
                            </button>
                        </form>

                        {/* DROITE : Formulaire Paiement (Conditionnel) */}
                        {clientSecret && !paymentSuccess ? (
                            <div style={{
                                marginTop: '40px', borderTop: '1px solid #E9D7C3', paddingTop: '30px'
                            }}>
                                <h3 className={styles.sectionTitle}>Paiement Securise</h3>

                                {/* Formulaire Stripe */}
                                <PaymentForm
                                    clientSecret={clientSecret}
                                    onSuccess={handlePaymentSuccess}
                                />
                            </div>
                        ) : (
                            !clientSecret && (
                                <p style={{
                                    color: '#888',
                                    marginTop: '60px',
                                    textAlign: 'center'
                                }}>
                                    Remplissez l'adresse pour continuer.
                                    <br/>
                                    <small>(Le formulaire de paiement apparaitra ensuite)</small>
                                </p>
                            )
                        )}
                    </div>
                </div>
            </div>
        </>
    );
};

export default CheckoutPage;
