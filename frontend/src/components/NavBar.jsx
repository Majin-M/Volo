import { useState, useEffect } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useCart } from '../contexts/CartContext';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import styles from './NavBar.module.css';

const NavBar = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const { cartCount } = useCart();
    const { isAuthenticated, user, logout } = useAuth();
    const { addToast } = useToast();
    const [menuOpen, setMenuOpen] = useState(false);

    useEffect(() => {
        setMenuOpen(false);
    }, [location.pathname]);

    const handleLogout = async () => {
        await logout();
        addToast('Deconnexion reussie', 'info');
        navigate('/');
    };

    return (
        <nav className={styles.nav}>
            <div className={styles.logo}>
                <Link to="/">
                    <img src="/images/Vologo.webp" alt="VOLO" className={styles.logoImage} />
                </Link>
            </div>

            <button
                type="button"
                className={styles.hamburger}
                onClick={() => setMenuOpen(!menuOpen)}
                aria-label={menuOpen ? 'Fermer le menu' : 'Ouvrir le menu'}
                aria-expanded={menuOpen}
            >
                <span className={`${styles.hamburgerLine} ${menuOpen ? styles.hamburgerLineTop : ''}`} />
                <span className={`${styles.hamburgerLine} ${menuOpen ? styles.hamburgerLineMid : ''}`} />
                <span className={`${styles.hamburgerLine} ${menuOpen ? styles.hamburgerLineBot : ''}`} />
            </button>

            <div className={`${styles.links} ${menuOpen ? styles.linksOpen : ''}`}>
                <Link to="/soins" className={styles.link}>Catalogue</Link>
                <Link to="/contact" className={styles.link}>Contact</Link>

                <Link
                    to="/panier"
                    className={styles.cartLink}
                    aria-label={`Panier, ${cartCount} article${cartCount > 1 ? 's' : ''}`}
                >
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
                    {cartCount > 0 && <span className={styles.badge}>{cartCount}</span>}
                </Link>

                {!isAuthenticated ? (
                    <Link to="/connexion" className={styles.link}>Connexion</Link>
                ) : (
                    <div className={styles.authSection}>
                        <Link to="/mon-compte" className={`${styles.link} ${styles.userName}`}>
                            {user?.firstName}
                        </Link>
                        <button type="button" onClick={handleLogout} className={styles.logoutButton}>
                            Deconnexion
                        </button>
                    </div>
                )}
            </div>
        </nav>
    );
};

export default NavBar;
