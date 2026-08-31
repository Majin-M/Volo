/*
===============================================================================
Page : ProductDetailPage
===============================================================================
Objectif :
    Afficher la fiche detaillee d'un produit et permettre son ajout au panier.

Responsabilites :
    - Charger le detail du produit depuis l'API au montage (selon l'id
      dans l'URL).
    - Gerer les etats d'interface (Chargement, Erreur, Introuvable, Succes).
    - Definir un titre et une description propres au produit (SEO) une fois
      les donnees chargees.
    - Afficher l'image, la marque, le prix, les problematiques ciblees,
      la description et les routines associees.
    - Permettre le choix d'une quantite et l'ajout au panier via CartContext.

Dependances :
    - fetchProductById (Function) : Pour recuperer le detail du produit.
    - useCart (Hook) : Pour ajouter le produit au panier.

Exemple d'utilisation :
    <Route path="/soins/:id" element={<ProductDetailPage />} />
===============================================================================
*/

import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { fetchProductById } from '../api/productApi';
import { useCart } from '../contexts/CartContext';
import { useToast } from '../contexts/ToastContext';
import Skeleton from '../components/Skeleton';
import styles from './ProductDetailPage.module.css';

const ProductDetailPage = () => {
    const { id } = useParams();
    const { addToCart } = useCart();
    const { addToast } = useToast();

    const [product, setProduct] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [quantity, setQuantity] = useState(1);
    const [added, setAdded] = useState(false);

    useEffect(() => {
        let cancelled = false;

        const loadProduct = async () => {
            setLoading(true);
            setError(null);
            setAdded(false);

            try {
                const response = await fetchProductById(id);
                if (!cancelled) {
                    setProduct(response.data);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err.message);
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        };

        loadProduct();
        return () => { cancelled = true; };
    }, [id]);

    /**
     * Ajoute le produit courant au panier autant de fois que la quantite
     * selectionnee.
     *
     * @returns {void}
     */
    const handleAddToCart = () => {
        for (let i = 0; i < quantity; i += 1) {
            addToCart(product);
        }
        setAdded(true);
        addToast(`${product.name} ajoute au panier`, 'success');
    };

    if (loading) {
        return (
            <div className={styles.container}>
                <div className={styles.layout}>
                    <Skeleton height="420px" borderRadius="12px" />
                    <div>
                        <Skeleton height="32px" width="70%" style={{ marginBottom: '12px' }} />
                        <Skeleton height="20px" width="40%" style={{ marginBottom: '20px' }} />
                        <Skeleton height="100px" />
                    </div>
                </div>
            </div>
        );
    }

    if (error || !product) {
        return (
            <>
                <Helmet>
                    <title>Produit introuvable — VOLO</title>
                    <meta name="robots" content="noindex" />
                </Helmet>
                <div className={styles.notFound}>
                    <h2>Produit introuvable</h2>
                    <p>Ce produit n'existe pas ou n'est plus disponible.</p>
                    <Link to="/soins" className={styles.backLink}>
                        ← Retour au catalogue
                    </Link>
                </div>
            </>
        );
    }

    const shortDescription = product.description
        ? product.description.slice(0, 160)
        : `${product.name} — decouvrez ce produit skincare chez VOLO.`;

    return (
        <div className={styles.container}>
            <Helmet>
                <title>{product.name} — VOLO</title>
                <meta name="description" content={shortDescription} />
            </Helmet>

            {/* Fil d'Ariane */}
            <nav className={styles.breadcrumb}>
                <Link to="/">Accueil</Link>
                <span className={styles.breadcrumbSep}>/</span>
                <Link to="/soins">Soins</Link>
                <span className={styles.breadcrumbSep}>/</span>
                <span className={styles.breadcrumbCurrent}>{product.name}</span>
            </nav>

            <div className={styles.layout}>
                {/* Image */}
                <div className={styles.imageWrapper}>
                    {product.imageUrl ? (
                        <img
                            src={`/images/products/${product.imageUrl}`}
                            alt={product.name}
                            className={styles.image}
                        />
                    ) : (
                        <div className={styles.imagePlaceholder}>Pas d'image</div>
                    )}
                </div>

                {/* Informations */}
                <div className={styles.info}>
                    {product.brand && (
                        <p className={styles.brand}>{product.brand.name}</p>
                    )}
                    <h1 className={styles.name}>{product.name}</h1>
                    <p className={styles.price}>{parseFloat(product.price).toFixed(2)} €</p>

                    {product.skinConcerns?.length > 0 && (
                        <div className={styles.tags}>
                            {product.skinConcerns.map((concern) => (
                                <span key={concern.id} className={styles.tag}>
                                    #{concern.slug}
                                </span>
                            ))}
                        </div>
                    )}

                    {product.description && (
                        <p className={styles.description}>{product.description}</p>
                    )}

                    {!product.isAvailable && (
                        <p className={styles.unavailable}>Actuellement indisponible</p>
                    )}

                    <div className={styles.actions}>
                        <div className={styles.quantityControl}>
                            <button
                                type="button"
                                onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                                aria-label="Diminuer la quantite"
                                className={styles.qtyButton}
                            >
                                −
                            </button>
                            <span className={styles.qtyValue}>{quantity}</span>
                            <button
                                type="button"
                                onClick={() => setQuantity((q) => q + 1)}
                                aria-label="Augmenter la quantite"
                                className={styles.qtyButton}
                            >
                                +
                            </button>
                        </div>

                        <button
                            type="button"
                            onClick={handleAddToCart}
                            disabled={!product.isAvailable}
                            className={styles.addButton}
                        >
                            {added ? 'Ajoute ✓' : 'Ajouter au panier'}
                        </button>
                    </div>
                </div>
            </div>

            {/* Routines associees */}
            {product.routines?.length > 0 && (
                <div className={styles.routinesSection}>
                    <h2 className={styles.sectionTitle}>Routines associees</h2>
                    <div className={styles.routinesGrid}>
                        {product.routines.map((routine) => (
                            <div key={routine.id} className={styles.routineCard}>
                                <span className={styles.routineLevel}>{routine.level}</span>
                                <p className={styles.routineName}>{routine.name}</p>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};

export default ProductDetailPage;
