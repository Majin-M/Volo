/*
===============================================================================
Page : CartPage (Panier)
===============================================================================
Objectif :
    Afficher le contenu du panier de l'utilisateur et permettre les actions
    de modification avant le passage a la commande.

Responsabilites :
    - Lister tous les articles presents dans le panier (image, nom, prix, quantite).
    - Permettre la mise a jour de la quantite (+ / -).
    - Permettre la suppression complete d'un article.
    - Afficher le sous-total par article et le total global.
    - Fournir un bouton pour proceder au paiement.
    - Empecher l'indexation de cette page par les moteurs de recherche
      (page privee, sans interet SEO).

Dependances :
    - useCart (Hook) : Pour acceder a cartItems, removeFromCart, updateQuantity, cartTotal.

Exemple d'utilisation :
    <Route path="/panier" element={<CartPage />} />
===============================================================================
*/

import { useState } from 'react';
import { useCart } from '../contexts/CartContext';
import { useToast } from '../contexts/ToastContext';
import { Link } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import ConfirmDialog from '../components/ConfirmDialog';
import styles from './CartPage.module.css';

// JSX statique (ne depend d'aucune prop/etat) : construit une seule fois.
const noindexTag = (
    <Helmet>
        <meta name="robots" content="noindex, nofollow" />
    </Helmet>
);

const CartPage = () => {
    const { cartItems, removeFromCart, updateQuantity, cartTotal } = useCart();
    const { addToast } = useToast();
    const [itemToRemove, setItemToRemove] = useState(null);

    const handleDecrease = (item) => {
        if (item.quantity <= 1) {
            setItemToRemove(item);
        } else {
            updateQuantity(item.id, item.quantity - 1);
        }
    };

    const handleConfirmRemove = () => {
        if (itemToRemove) {
            removeFromCart(itemToRemove.id);
            addToast(`${itemToRemove.name} retire du panier`, 'info');
            setItemToRemove(null);
        }
    };

    const handleIncrease = (item) => {
        updateQuantity(item.id, item.quantity + 1);
    };

    // Etat : Panier vide
    if (cartItems.length === 0) {
        return (
            <>
                {noindexTag}
                <div className={styles.emptyContainer}>
                    <svg width="120" height="120" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style={{ marginBottom: '20px', opacity: 0.7 }}>
                        <circle cx="60" cy="60" r="55" fill="#F8F0E8" stroke="#E9D7C3" strokeWidth="2" />
                        <path d="M35 45h6l8 30h22l8-22H50" stroke="#5F4C42" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" fill="none" />
                        <circle cx="55" cy="85" r="4" fill="#5F4C42" />
                        <circle cx="75" cy="85" r="4" fill="#5F4C42" />
                        <line x1="50" y1="58" x2="78" y2="58" stroke="#E9D7C3" strokeWidth="2" strokeLinecap="round" strokeDasharray="4 4" />
                    </svg>
                    <h2 className={styles.emptyTitle}>Votre panier est vide</h2>
                    <p className={styles.emptyText}>
                        Decouvrez nos soins et trouvez la routine qui vous correspond.
                    </p>
                    <Link to="/soins" className={styles.emptyButton}>
                        Voir le catalogue
                    </Link>
                </div>
            </>
        );
    }

    // Etat : Panier rempli
    return (
        <>
            {noindexTag}
            <div className={styles.container}>
                <h1 className={styles.pageTitle}>Mon Panier</h1>

                <div className={styles.layout}>
                    {/* Liste des articles */}
                    <div className={styles.itemsList}>
                        {cartItems.map((item) => (
                            <div key={item.id} className={styles.itemCard}>
                                <div className={styles.itemImageWrapper}>
                                    {item.imageUrl ? (
                                        <img
                                            src={`/images/products/${item.imageUrl}`}
                                            alt={item.name}
                                            className={styles.itemImage}
                                        />
                                    ) : (
                                        <div className={styles.itemImagePlaceholder}>Pas d'image</div>
                                    )}
                                </div>

                                <div className={styles.itemInfo}>
                                    <h3 className={styles.itemName}>{item.name}</h3>
                                    <p className={styles.itemPrice}>
                                        {parseFloat(item.price).toFixed(2)} €
                                    </p>
                                </div>

                                <div className={styles.quantityControl}>
                                    <button
                                        type="button"
                                        className={styles.qtyButton}
                                        onClick={() => handleDecrease(item)}
                                        aria-label="Diminuer la quantite"
                                    >
                                        −
                                    </button>
                                    <span className={styles.qtyValue}>{item.quantity}</span>
                                    <button
                                        type="button"
                                        className={styles.qtyButton}
                                        onClick={() => handleIncrease(item)}
                                        aria-label="Augmenter la quantite"
                                    >
                                        +
                                    </button>
                                </div>

                                <div className={styles.itemSubtotal}>
                                    {(parseFloat(item.price) * item.quantity).toFixed(2)} €
                                </div>

                                <button
                                    type="button"
                                    className={styles.removeButton}
                                    onClick={() => setItemToRemove(item)}
                                    aria-label="Supprimer l'article"
                                >
                                    ✕
                                </button>
                            </div>
                        ))}
                    </div>

                    {/* Recapitulatif */}
                    <div className={styles.summaryCard}>
                        <h2 className={styles.summaryTitle}>Recapitulatif</h2>
                        <div className={styles.summaryRow}>
                            <span>Sous-total</span>
                            <span>{cartTotal.toFixed(2)} €</span>
                        </div>
                        <div className={styles.summaryRow}>
                            <span>Livraison</span>
                            <span className={styles.freeShipping}>Offerte</span>
                        </div>
                        <div className={styles.summaryDivider} />
                        <div className={styles.summaryTotal}>
                            <span>Total</span>
                            <span>{cartTotal.toFixed(2)} €</span>
                        </div>

                        <Link to="/commande" className={styles.checkoutButton}>
                            Passer la commande
                        </Link>

                        <Link to="/soins" className={styles.continueLink}>
                            ← Continuer mes achats
                        </Link>
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={!!itemToRemove}
                title="Retirer du panier ?"
                message={itemToRemove ? `"${itemToRemove.name}" sera supprime de votre panier.` : ''}
                confirmLabel="Retirer"
                onConfirm={handleConfirmRemove}
                onCancel={() => setItemToRemove(null)}
                danger
            />
        </>
    );
};

export default CartPage;
