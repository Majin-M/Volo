/*
===============================================================================
Page : OrderConfirmationPage (Confirmation de commande)
===============================================================================
Objectif :
    Afficher un recapitulatif apres un paiement accepte, confirmant
    la bonne prise en compte de la commande.

Responsabilites :
    - Recuperer les donnees de commande transmises via le state de
      navigation (location.state.order).
    - Rediriger vers l'accueil si aucune donnee de commande n'est
      presente (acces direct interdit).
    - Afficher la reference, le montant et le statut du paiement.
    - Proposer des liens vers l'historique des commandes et le catalogue.

Dependances :
    - react-router-dom : useLocation (donnees commande), useNavigate
      (navigation), Navigate (redirection).

Exemple d'utilisation :
    <Route path="/confirmation" element={<OrderConfirmationPage />} />
===============================================================================
*/

import { useLocation, useNavigate, Navigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import styles from './OrderConfirmationPage.module.css';

const OrderConfirmationPage = () => {
    const location = useLocation();
    const navigate = useNavigate();
    const order = location.state?.order;

    // Si on arrive sans donnees de commande (acces direct), rediriger
    if (!order) {
        return <Navigate to="/" replace />;
    }

    return (
        <>
            <Helmet>
                <meta name="robots" content="noindex, nofollow" />
                <title>Commande confirmee — VOLO</title>
            </Helmet>

            <div className={styles.container}>
                <div className={styles.card}>
                    <div className={styles.iconWrapper}>
                        <svg className={styles.checkIcon} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M20 6L9 17l-5-5" />
                        </svg>
                    </div>

                    <h1 className={styles.title}>Commande confirmee</h1>
                    <p className={styles.subtitle}>
                        Merci pour votre achat ! Votre paiement a ete accepte.
                    </p>

                    <div className={styles.details}>
                        <div className={styles.detailRow}>
                            <span className={styles.detailLabel}>Reference</span>
                            <span className={styles.detailValue}>CMD-{order.orderId}</span>
                        </div>
                        <div className={styles.detailRow}>
                            <span className={styles.detailLabel}>Montant</span>
                            <span className={styles.detailValue}>{parseFloat(order.amount).toFixed(2)} €</span>
                        </div>
                        <div className={styles.detailRow}>
                            <span className={styles.detailLabel}>Statut</span>
                            <span className={styles.statusBadge}>Paiement accepte</span>
                        </div>
                    </div>

                    <p className={styles.info}>
                        Un email de confirmation vous sera envoye prochainement.
                    </p>

                    <div className={styles.actions}>
                        <button
                            type="button"
                            className={styles.primaryButton}
                            onClick={() => navigate('/mes-commandes')}
                        >
                            Voir mes commandes
                        </button>
                        <button
                            type="button"
                            className={styles.secondaryButton}
                            onClick={() => navigate('/soins')}
                        >
                            Continuer mes achats
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
};

export default OrderConfirmationPage;
