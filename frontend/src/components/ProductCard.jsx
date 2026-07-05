/*
===============================================================================
Composant : ProductCard
===============================================================================
Objectif :
    Afficher la vignette d'un produit individuel dans la grille du catalogue.

Responsabilites :
    - Afficher les informations essentielles (Nom, Prix, Marque).
    - Afficher l'image uploadee par l'Admin ou un placeholder.
    - Afficher les tags/problematiques liees au produit.
    - Permettre l'action d'ajout au panier via un bouton.

Parametres (Props) :
    - product (object) : L'objet produit contenant id, name, price, brand, skinConcerns.
    - onAddToCart (function) : Fonction callback declenchee au clic sur "Ajouter".

Exemple d'utilisation :
    <ProductCard
        product={currentProduct}
        onAddToCart={() => addToCart(currentProduct)}
    />
===============================================================================
*/

import React from 'react';
import styles from './ProductCard.module.css';

const ProductCard = ({ product, onAddToCart }) => {

    return (
        <div className={styles.card}>
            <div>
                {product.imageUrl ? (
                    <img
                        src={`http://127.0.0.1:8000/images/products/${product.imageUrl}`}
                        alt={product.name}
                        className={styles.productImage}
                    />
                ) : (
                    <div className={styles.imageContainer}>
                        Pas d'image
                    </div>
                )}

                <h3 className={styles.title}>
                    {product.name}
                </h3>

                <p className={styles.brand}>
                    {product.brand?.name}
                </p>

                <p className={styles.price}>
                    {product.price} €
                </p>

                <div className={styles.tags}>
                    {product.skinConcerns?.map(concern => (
                        <span
                            key={concern.id}
                            className={styles.tag}
                        >
                            #{concern.slug}
                        </span>
                    ))}
                </div>
            </div>

            <button
                type="button"
                className={styles.button}
                onClick={() => onAddToCart(product)}
            >
                Ajouter au panier
            </button>
        </div>
    );
};

export default ProductCard;
