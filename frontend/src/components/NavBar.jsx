/*
===============================================================================
Composant : NavBar (Barre de Navigation)
===============================================================================
Objectif :
    Afficher la barre de navigation principale du site.

Responsabilites :
    - Afficher le logo/nom de la marque "VOLO".
    - Permettre la navigation vers le Catalogue et le Panier.
    - Afficher un compteur d'articles dynamique sur l'icone panier.
    - Gerer l'affichage conditionnel des liens (Connexion / Deconnexion).
    - Gerer la deconnexion : logout() appelle l'API pour supprimer le
      cookie HttpOnly cote serveur, avant de rediriger.

Dependances :
    - useCart (Hook) : Pour acceder a cartCount.
    - useAuth (Hook) : Pour l'etat de connexion et logout().
    - useNavigate (Hook) : Pour la redirection apres deconnexion.

Exemple d'utilisation :
    <NavBar />
===============================================================================
*/

import { Link, useNavigate } from 'react-router-dom';
import { useCart } from '../contexts/CartContext';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';

// Styles statiques (ne dependent d'aucune prop/etat) : definis en portee
// module pour ne pas etre reconstruits a chaque rendu.
const navStyle = {
    backgroundColor: '#fff',
    padding: '15px 40px',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderBottom: '2px solid #E9D7C3',
    position: 'sticky',
    top: 0,
    zIndex: 1000,
    boxShadow: '0 2px 4px rgba(0,0,0,0.05)',
};

const logoImageStyle = {
    height: '65px',
    display: 'block',
};

const linkStyle = {
    color: '#5F4C42',
    textDecoration: 'none',
    margin: '0 15px',
    fontWeight: '500',
    cursor: 'pointer',
};

const cartLinkStyle = {
    display: 'flex',
    alignItems: 'center',
    position: 'relative',
    marginRight: '20px',
    color: '#5F4C42',
};

const badgeStyle = {
    position: 'absolute',
    top: '-8px',
    right: '-10px',
    backgroundColor: '#5F4C42',
    color: '#fff',
    borderRadius: '50%',
    width: '20px',
    height: '20px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '0.75em',
    fontWeight: 'bold',
    border: '2px solid #fff',
};

const logoutButtonStyle = {
    background: 'none',
    border: '1px solid #5F4C42',
    color: '#5F4C42',
    padding: '5px 10px',
    borderRadius: '4px',
    cursor: 'pointer',
    fontSize: '0.9em',
};

const NavBar = () => {
    const navigate = useNavigate();
    const { cartCount } = useCart();
    const { isAuthenticated, user, logout } = useAuth();
    const { addToast } = useToast();

    /**
     * Deconnecte l'utilisateur (supprime le cookie cote serveur) puis
     * redirige vers l'accueil.
     *
     * @returns {Promise<void>}
     */
    const handleLogout = async () => {
        await logout();
        addToast('Deconnexion reussie', 'info');
        navigate('/');
    };

    return (
        <nav style={navStyle}>
            <div>
                <Link to="/">
                    <img src="/images/Vologo.webp" alt="VOLO" style={logoImageStyle} />
                </Link>
            </div>

            <div style={{ display: 'flex', alignItems: 'center' }}>
                <Link to="/soins" style={linkStyle}>Catalogue</Link>

                <Link to="/panier" style={cartLinkStyle} aria-label={`Panier, ${cartCount} article${cartCount > 1 ? 's' : ''}`}>
                    <svg
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                    >
                        <circle cx="9" cy="21" r="1" />
                        <circle cx="20" cy="21" r="1" />
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                    </svg>
                    {cartCount > 0 && <span style={badgeStyle}>{cartCount}</span>}
                </Link>

                {!isAuthenticated ? (
                    <Link to="/connexion" style={linkStyle}>Connexion</Link>
                ) : (
                    <div style={{ display: 'flex', alignItems: 'center', marginLeft: '15px' }}>
                        <Link to="/mon-compte" style={{ ...linkStyle, fontSize: '0.9em' }}>
                            {user?.firstName}
                        </Link>
                        <button type="button" onClick={handleLogout} style={logoutButtonStyle}>
                            Deconnexion
                        </button>
                    </div>
                )}
            </div>
        </nav>
    );
};

export default NavBar;
