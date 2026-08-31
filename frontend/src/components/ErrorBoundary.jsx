/*
===============================================================================
Composant : ErrorBoundary
===============================================================================
Objectif :
    Capturer les erreurs JavaScript dans l'arbre React et afficher une
    interface de secours au lieu d'un ecran blanc.

Responsabilites :
    - Intercepter les erreurs de rendu, de cycle de vie et des constructeurs
      des composants enfants (via componentDidCatch / getDerivedStateFromError).
    - Afficher un message clair avec un bouton « Recharger » pour que
      l'utilisateur puisse reprendre sans manipulation technique.
    - Journaliser l'erreur dans la console pour le debug.

Limitations :
    - Ne capture PAS les erreurs dans les gestionnaires d'evenements,
      le code asynchrone, ni le rendu cote serveur (limitation React).

Exemple d'utilisation :
    <ErrorBoundary>
      <App />
    </ErrorBoundary>
===============================================================================
*/

import { Component } from 'react';

class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError() {
        return { hasError: true };
    }

    componentDidCatch(error, errorInfo) {
        console.error('ErrorBoundary caught:', error, errorInfo);
    }

    render() {
        if (this.state.hasError) {
            return (
                <div style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                    minHeight: '100vh',
                    backgroundColor: '#F8F0E8',
                    fontFamily: "'Lato', sans-serif",
                    padding: '20px',
                    textAlign: 'center',
                }}>
                    <h1 style={{
                        fontFamily: "'Playfair Display', serif",
                        color: '#5F4C42',
                        fontSize: '1.8em',
                        marginBottom: '16px',
                    }}>
                        Une erreur est survenue
                    </h1>
                    <p style={{
                        color: '#7a6a60',
                        fontSize: '1.05em',
                        marginBottom: '32px',
                        maxWidth: '400px',
                    }}>
                        L'application a rencontre un probleme inattendu.
                        Veuillez recharger la page.
                    </p>
                    <button
                        type="button"
                        onClick={() => window.location.reload()}
                        style={{
                            backgroundColor: '#5F4C42',
                            color: '#fff',
                            border: 'none',
                            padding: '15px 40px',
                            borderRadius: '25px',
                            fontSize: '1.05em',
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        Recharger la page
                    </button>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
