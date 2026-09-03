/*
===============================================================================
Page : OrderHistoryPage (Historique des commandes)
===============================================================================
Objectif :
    Afficher la liste des commandes passees par l'utilisateur connecte.

Responsabilites :
    - Charger les commandes depuis l'API au montage du composant.
    - Gerer les etats d'interface (Chargement, Erreur, Liste vide).
    - Afficher chaque commande avec sa reference, sa date, son statut,
      le detail des articles et le montant total.
    - Traduire les statuts techniques en libelles lisibles (pending,
      paid, shipped, delivered, cancelled).

Dependances :
    - apiCall (Function) : Pour recuperer les commandes via GET /orders.
    - react-router-dom : useNavigate (redirection vers le catalogue).

Exemple d'utilisation :
    <Route path="/mes-commandes" element={<OrderHistoryPage />} />
===============================================================================
*/

import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { apiCall } from '../api/api';
import styles from './OrderHistoryPage.module.css';

const STATUS_LABELS = {
    pending: 'En attente',
    paid: 'Payee',
    shipped: 'Expediee',
    delivered: 'Livree',
    cancelled: 'Annulee',
};

const STATUS_CLASSES = {
    pending: 'statusPending',
    paid: 'statusPaid',
    shipped: 'statusShipped',
    delivered: 'statusDelivered',
    cancelled: 'statusCancelled',
};

const OrderHistoryPage = () => {
    const navigate = useNavigate();
    const [orders, setOrders] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchOrders = async () => {
            try {
                const response = await apiCall('/orders');
                setOrders(response.data || []);
            } catch {
                setError('Impossible de charger vos commandes.');
            } finally {
                setLoading(false);
            }
        };
        fetchOrders();
    }, []);

    if (loading) {
        return (
            <div className={styles.container}>
                <div className={styles.loading}>Chargement...</div>
            </div>
        );
    }

    return (
        <>
            <Helmet>
                <title>Mes commandes — VOLO</title>
                <meta name="description" content="Retrouvez l'historique de vos commandes VOLO : statut, details et suivi." />
                <meta name="robots" content="noindex, nofollow" />
            </Helmet>

            <div className={styles.container}>
                <h1 className={styles.title}>Mes commandes</h1>

                {error && <div className={styles.errorMsg}>{error}</div>}

                {orders.length === 0 && !error ? (
                    <div className={styles.emptyState}>
                        <svg width="120" height="120" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style={{ marginBottom: '20px', opacity: 0.7 }}>
                            <circle cx="60" cy="60" r="55" fill="#F8F0E8" stroke="#E9D7C3" strokeWidth="2" />
                            <rect x="35" y="30" width="50" height="60" rx="4" fill="#fff" stroke="#5F4C42" strokeWidth="2" />
                            <line x1="45" y1="45" x2="75" y2="45" stroke="#E9D7C3" strokeWidth="2" strokeLinecap="round" />
                            <line x1="45" y1="55" x2="70" y2="55" stroke="#E9D7C3" strokeWidth="2" strokeLinecap="round" />
                            <line x1="45" y1="65" x2="65" y2="65" stroke="#E9D7C3" strokeWidth="2" strokeLinecap="round" />
                            <line x1="45" y1="75" x2="60" y2="75" stroke="#E9D7C3" strokeWidth="2" strokeLinecap="round" />
                        </svg>
                        <p>Vous n'avez pas encore passe de commande.</p>
                        <button
                            type="button"
                            className={styles.primaryButton}
                            onClick={() => navigate('/soins')}
                        >
                            Decouvrir nos soins
                        </button>
                    </div>
                ) : (
                    <div className={styles.orderList}>
                        {orders.map((order) => (
                            <div key={order.id} className={styles.orderCard}>
                                <div className={styles.orderHeader}>
                                    <div>
                                        <span className={styles.orderRef}>{order.reference}</span>
                                        <span className={styles.orderDate}>
                                            {new Date(order.createdAt).toLocaleDateString('fr-FR', {
                                                day: 'numeric',
                                                month: 'long',
                                                year: 'numeric',
                                            })}
                                        </span>
                                    </div>
                                    <span className={`${styles.statusBadge} ${styles[STATUS_CLASSES[order.status]] || ''}`}>
                                        {STATUS_LABELS[order.status] || order.status}
                                    </span>
                                </div>

                                <div className={styles.orderItems}>
                                    {(order.items || []).map((item) => (
                                        <div key={item.id ?? item.productName} className={styles.itemRow}>
                                            <span className={styles.itemName}>
                                                {item.productName || 'Produit'}
                                            </span>
                                            <span className={styles.itemQty}>x{item.quantity}</span>
                                            <span className={styles.itemPrice}>
                                                {parseFloat(item.unitPrice).toFixed(2)} €
                                            </span>
                                        </div>
                                    ))}
                                </div>

                                <div className={styles.orderFooter}>
                                    <span className={styles.totalLabel}>Total</span>
                                    <span className={styles.totalValue}>
                                        {parseFloat(order.total).toFixed(2)} €
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
};

export default OrderHistoryPage;
