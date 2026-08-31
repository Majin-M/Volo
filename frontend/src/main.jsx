/*
===============================================================================
Module : main (Point d'entree React)
===============================================================================
Objectif :
    Initialiser l'application React et monter l'arbre de composants
    dans le DOM.

Responsabilites :
    - Creer la racine React sur l'element #root.
    - Envelopper l'application dans les Providers globaux :
        1. React.StrictMode   — Detection des problemes en developpement.
        2. ErrorBoundary       — Capture les erreurs JS (evite l'ecran blanc).
        3. HelmetProvider      — Gestion des balises <head> (SEO).
        4. AuthProvider        — Contexte d'authentification utilisateur.
        5. CartProvider        — Contexte du panier d'achat.
    - Importer la feuille de style globale (index.css).

Dependances :
    - react, react-dom          : Rendu de l'application.
    - react-helmet-async        : SEO et meta-tags.
    - AuthContext, CartContext   : Providers globaux.
===============================================================================
*/

import React from 'react'
import ReactDOM from 'react-dom/client'
import { HelmetProvider } from 'react-helmet-async'
import App from './App.jsx'
import ErrorBoundary from './components/ErrorBoundary.jsx'
import './index.css'

import { CartProvider } from './contexts/CartContext';
import { AuthProvider } from './contexts/AuthContext';
import { ToastProvider } from './contexts/ToastContext';

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <ErrorBoundary>
      <HelmetProvider>
        <AuthProvider>
          <CartProvider>
            <ToastProvider>
              <App />
            </ToastProvider>
          </CartProvider>
        </AuthProvider>
      </HelmetProvider>
    </ErrorBoundary>
  </React.StrictMode>,
)
