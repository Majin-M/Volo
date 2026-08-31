/*
===============================================================================
Context : CartContext (Panier)
===============================================================================
Objectif :
    Gerer l'etat du panier d'achat de l'utilisateur a l'echelle
    de l'application React.

Responsabilites :
    - Stocker la liste des articles selectionnes (initialisee directement
      depuis localStorage, sans passer par un effect au montage).
    - Calculer le nombre total d'articles et le prix total.
    - Persister le panier dans le localStorage du navigateur a chaque
      modification.
    - Proposer des fonctions pour ajouter, retirer ou modifier la quantite
      des produits.

Fonctions exposees (via le Provider) :
    - addToCart(product)              : Ajoute un produit au panier.
    - removeFromCart(id)              : Retire un produit du panier.
    - updateQuantity(id, quantity)    : Met a jour la quantite d'un produit.
    - clearCart()                     : Vide le panier.
    - cartItems                       : Liste actuelle des items.
    - cartTotal                       : Montant total du panier.
    - cartCount                       : Nombre total d'articles.

Exemple d'utilisation :
    const { addToCart, cartTotal } = useCart();
    ...
    <button onClick={() => addToCart(product)}>Ajouter</button>
===============================================================================
*/

import { createContext, use, useState, useEffect, useMemo } from 'react';

const CART_STORAGE_KEY = 'volo_cart:v1';

const CartContext = createContext();

/**
 * Hook personnalise pour acceder au contexte du panier.
 * @returns {object} L'etat et les methodes du panier.
 */
// eslint-disable-next-line react-refresh/only-export-components
export const useCart = () => {
    const context = use(CartContext);
    if (!context) {
        throw new Error('useCart doit etre utilise a l\'interieur d\'un CartProvider');
    }
    return context;
};

/**
 * Fournisseur du Contexte Cart.
 * Enveloppe l'application pour fournir les fonctions du panier a tous les composants enfants.
 */
export const CartProvider = ({ children }) => {
    // Initialisation directe depuis localStorage (lazy initializer) : evite
    // un premier rendu avec un panier vide suivi d'un second rendu une fois
    // l'effect execute, ce qui produisait un flash visuel au chargement.
    const [cartItems, setCartItems] = useState(() => {
        const storedCart = localStorage.getItem(CART_STORAGE_KEY);
        return storedCart ? JSON.parse(storedCart) : [];
    });

    /**
     * Sauvegarde automatique du panier dans le localStorage a chaque fois
     * que la liste 'cartItems' change.
     */
    useEffect(() => {
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cartItems));
    }, [cartItems]);

    /**
     * Ajoute un produit au panier.
     * Si le produit existe deja, incremente la quantite.
     * Sinon, ajoute une nouvelle entree.
     *
     * @param {object} product - L'objet produit complet (id, name, price, etc.)
     */
    const addToCart = (product) => {
        setCartItems((prevItems) => {
            const existingItem = prevItems.find((item) => item.id === product.id);

            if (existingItem) {
                return prevItems.map((item) =>
                    item.id === product.id
                        ? { ...item, quantity: item.quantity + 1 }
                        : item
                );
            } else {
                return [...prevItems, { ...product, quantity: 1 }];
            }
        });
    };

    /**
     * Retire un produit du panier selon son ID.
     *
     * @param {number} id - L'identifiant unique du produit
     */
    const removeFromCart = (id) => {
        setCartItems((prevItems) => prevItems.filter((item) => item.id !== id));
    };

    /**
     * Met a jour la quantite d'un produit existant dans le panier.
     * Si la nouvelle quantite est inferieure a 1, retire l'article.
     *
     * @param {number} id - L'identifiant unique du produit
     * @param {number} newQuantity - La nouvelle quantite souhaitee
     */
    const updateQuantity = (id, newQuantity) => {
        if (newQuantity < 1) {
            removeFromCart(id);
            return;
        }

        setCartItems((prevItems) =>
            prevItems.map((item) =>
                item.id === id
                    ? { ...item, quantity: newQuantity }
                    : item
            )
        );
    };

    /**
     * Vide completement le panier.
     * Utile apres validation de la commande.
     */
    const clearCart = () => {
        setCartItems([]);
        localStorage.removeItem(CART_STORAGE_KEY);
    };

    /**
     * Calcule le nombre total d'articles (tous produits confondus).
     * @returns {number}
     */
    const cartCount = cartItems.reduce((acc, item) => acc + item.quantity, 0);

    /**
     * Calcule le prix total du panier.
     * @returns {number}
     */
    const cartTotal = cartItems.reduce(
        (acc, item) => acc + parseFloat(item.price) * item.quantity,
        0
    );

    const value = useMemo(() => ({
        cartItems,
        addToCart,
        removeFromCart,
        updateQuantity,
        clearCart,
        cartCount,
        cartTotal,
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }), [cartItems, cartCount, cartTotal]);

    return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
};
