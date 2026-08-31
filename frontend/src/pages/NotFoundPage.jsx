/*
===============================================================================
Page : NotFoundPage (404)
===============================================================================
Objectif :
    Page affichee lorsque l'URL ne correspond a aucune route connue.

Responsabilites :
    - Indiquer clairement que la page n'existe pas.
    - Proposer des actions concretes (retour accueil, catalogue).
    - Empecher l'indexation par les moteurs de recherche.

Exemple d'utilisation :
    <Route path="*" element={<NotFoundPage />} />
===============================================================================
*/

import { Link } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import styles from './NotFoundPage.module.css';

const NotFoundPage = () => (
    <>
        <Helmet>
            <title>Page introuvable — VOLO</title>
            <meta name="robots" content="noindex, nofollow" />
        </Helmet>

        <div className={styles.container}>
            {/* Illustration SVG inline */}
            <svg
                className={styles.illustration}
                viewBox="0 0 200 160"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
            >
                <rect x="40" y="30" width="120" height="90" rx="8" fill="#E9D7C3" />
                <rect x="50" y="40" width="100" height="70" rx="4" fill="#fff" />
                <circle cx="80" cy="70" r="8" fill="#d9534f" opacity="0.6" />
                <circle cx="120" cy="70" r="8" fill="#d9534f" opacity="0.6" />
                <path d="M85 92 C95 84, 105 84, 115 92" stroke="#d9534f" strokeWidth="3" strokeLinecap="round" fill="none" opacity="0.6" />
                <rect x="10" y="125" width="180" height="6" rx="3" fill="#E9D7C3" />
                <rect x="60" y="135" width="80" height="6" rx="3" fill="#E9D7C3" opacity="0.5" />
            </svg>

            <h1 className={styles.code}>404</h1>
            <h2 className={styles.title}>Page introuvable</h2>
            <p className={styles.description}>
                La page que vous cherchez n'existe pas ou a ete deplacee.
            </p>

            <div className={styles.actions}>
                <Link to="/" className={styles.primaryBtn}>
                    Retour a l'accueil
                </Link>
                <Link to="/soins" className={styles.secondaryBtn}>
                    Voir le catalogue
                </Link>
            </div>
        </div>
    </>
);

export default NotFoundPage;
