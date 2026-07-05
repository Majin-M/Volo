/*
===============================================================================
Composant : App
===============================================================================
Objectif :
    Composant racine qui enveloppe l'application.

Responsabilites :
    - Envelopper l'application avec les Contextes (Cart) et Stripe.
    - Definir la structure des routes (Routing).
    - Integrer la mise en page dynamique (Header/Footer) via AppWrapper.
    - Assurer la compatibilite avec React Router.

Dependances :
    - React, Router, Contexts.
    - Stripe (@stripe/react-stripe-js).

Exemple d'utilisation :
    ReactDOM.createRoot(document.getElementById('root')).render(<App />);
===============================================================================
*/

import React from 'react';
import { BrowserRouter, Routes, Route, useLocation } from 'react-router-dom';
import { loadStripe } from '@stripe/stripe-js';
import { Elements } from '@stripe/react-stripe-js';

// Composants Layouts
import NavBar from './components/NavBar';
import Footer from './components/Footer';

// Pages
import HomePage from './pages/HomePage';
import ProductListPage from './pages/ProductListPage';
import ProductDetailPage from './pages/ProductDetailPage';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import CartPage from './pages/CartPage';
import CheckoutPage from './pages/CheckoutPage';
import ContactPage from './pages/ContactPage';

// Configuration Stripe (Cle publique de test)
const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLIC_KEY);

const appStyle = {
    minHeight: '100vh',
    display: 'flex',
    flexDirection: 'column',
    backgroundColor: '#F8F0E8',
    fontFamily: "'Lato', sans-serif",
};

function App() {
    return (
        <Elements stripe={stripePromise}>
            <BrowserRouter>
                <AppWrapper />
            </BrowserRouter>
        </Elements>
    );
}

/**
 * Composant Interne : AppWrapper
 * Objectif : Gerer la mise en page des elements communs (Nav/Footer).
 */
function AppWrapper() {
    const location = useLocation();

    return (
        <div style={appStyle}>
            <NavBar location={location} />
            <main style={{ flex: 1 }}>
                <Routes>
                    <Route path="/" element={<HomePage />} />
                    <Route path="/soins" element={<ProductListPage />} />
                    <Route path="/soins/:id" element={<ProductDetailPage />} />
                    <Route path="/panier" element={<CartPage />} />
                    <Route path="/connexion" element={<LoginPage />} />
                    <Route path="/inscription" element={<RegisterPage />} />
                    <Route path="/commande" element={<CheckoutPage />} />
                    <Route path="/contact" element={<ContactPage />} />
                </Routes>
            </main>
            <Footer />
        </div>
    );
}

export default App;
