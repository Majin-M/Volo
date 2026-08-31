/*
===============================================================================
Composant : PaymentForm
===============================================================================
Objectif :
    Afficher le formulaire de paiement securise via Stripe Elements.

Responsabilites :
    - Afficher le champ carte bancaire de facon securisee (CardElement).
    - Gerer l'etat de chargement (Spinner).
    - Gerer les erreurs de validation (Carte invalide, echec paiement).
    - Notifier le parent (CheckoutPage) en cas de succes.

Dependances :
    - useStripe, useElements, CardElement (de @stripe/react-stripe-js).
    - onSuccess (function) : Callback declenche apres paiement reussi.

Parametres (Props) :
    - clientSecret (string) : Token fourni par le Backend pour initialiser Stripe Elements.

Exemple d'utilisation :
    <PaymentForm
        clientSecret={secret}
        onSuccess={onSuccess}
    />
===============================================================================
*/

import { useState } from 'react';
import { useStripe, useElements, CardElement } from '@stripe/react-stripe-js';

// Styles statiques (ne dependent d'aucune prop/etat) : definis en portee
// module pour ne pas etre reconstruits a chaque rendu.
const cardOptions = {
    hidePostalCode: true,
    style: {
        base: {
            color: '#32325d',
            fontFamily: 'Inter, system-ui, sans-serif',
            fontSize: '16px',
            '::placeholder': {
                color: '#aab7c4',
            },
        },
        invalid: {
            color: '#fa755a',
            iconColor: '#fa755a',
        },
    },
};

const lockIconStyle = { textAlign: 'center', marginBottom: '10px' };
const errorStyle = { color: 'red', textAlign: 'center', marginTop: '15px' };

const PaymentForm = ({ clientSecret, onSuccess }) => {
    const stripe = useStripe();
    const elements = useElements();
    const [error, setError] = useState(null);
    const [processing, setProcessing] = useState(false);

    /**
     * Validation de la saisie et envoi a Stripe.
     */
    const handleSubmit = async (event) => {
        event.preventDefault();

        if (!stripe || !elements) return;

        setProcessing(true);
        setError(null);

        const cardElement = elements.getElement(CardElement);

        try {
            const { error: paymentError, paymentIntent } = await stripe.confirmCardPayment(
                clientSecret,
                {
                    payment_method: {
                        card: cardElement,
                        billing_details: {
                            name: 'Client VOLO',
                        },
                    },
                }
            );

            if (paymentError) {
                setError("Le paiement a echoue. Verifiez vos coordonnees.");
            } else if (paymentIntent) {
                setError(null);
                if (onSuccess) {
                    onSuccess(paymentIntent);
                }
            }
        } finally {
            setProcessing(false);
        }
    };

    const buttonStyle = {
        width: '100%',
        padding: '15px',
        marginTop: '15px',
        backgroundColor: '#6772e5',
        color: '#fff',
        border: 'none',
        borderRadius: '4px',
        fontSize: '1em',
        cursor: processing ? 'not-allowed' : 'pointer',
        opacity: processing ? 0.7 : 1,
    };

    return (
        <form id="payment-form" onSubmit={handleSubmit}>
            <div style={lockIconStyle}>
                🔐
            </div>

            <CardElement options={cardOptions} id="card-element" />

            <button
                type="submit"
                disabled={processing || !stripe}
                style={buttonStyle}
            >
                {processing ? 'Paiement en cours...' : 'Payer maintenant'}
            </button>

            {error && (
                <div style={errorStyle}>
                    {error}
                </div>
            )}
        </form>
    );
};

export default PaymentForm;
