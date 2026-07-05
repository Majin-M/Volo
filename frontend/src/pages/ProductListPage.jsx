/*
===============================================================================
Page : ProductListPage (Catalogue)
===============================================================================
Objectif :
    Page principale du catalogue affichant la grille complete des produits.

Responsabilites :
    - Charger la liste des produits depuis l'API au montage.
    - Definir un titre adapte au filtre de problematique actif (SEO).
    - Gerer les etats d'interface (Chargement, Erreur, Succes).
    - Integrer le contexte du panier pour permettre l'ajout au panier.
    - Afficher les produits via le composant ProductCard.

Exemple d'utilisation :
    <Route path="/soins" element={<ProductListPage />} />
===============================================================================
*/

import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { fetchProducts } from '../api/productApi';
import { useCart } from '../contexts/CartContext';
import ProductCard from '../components/ProductCard';
import Skeleton from '../components/Skeleton';

const ProductListPage = () => {
    const [products, setProducts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const { addToCart } = useCart();
    const [searchParams] = useSearchParams();
    const concernSlug = searchParams.get('skin_concern');

    useEffect(() => {
        const loadProducts = async () => {
            try {
                const slugFromUrl = searchParams.get('skin_concern');
                const params = {};
                if (slugFromUrl) {
                    params.skin_concern = slugFromUrl;
                }

                const response = await fetchProducts(params);
                setProducts(response.data);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };

        loadProducts();
    }, [searchParams]);

    const pageTitle = concernSlug
        ? `Produits pour ${concernSlug.replace('-', ' ')} — VOLO`
        : 'Catalogue — VOLO';
    const pageDescription = concernSlug
        ? `Decouvrez notre selection de produits skincare adaptes a votre peau : ${concernSlug.replace('-', ' ')}.`
        : 'Explorez notre catalogue de soins skincare, adaptes a chaque type de peau.';

    if (loading) {
        return (
            <>
                <Helmet>
                    <title>{pageTitle}</title>
                    <meta name="description" content={pageDescription} />
                </Helmet>
                <div style={{ display: 'flex', gap: '30px', flexWrap: 'wrap', justifyContent: 'center' }}>
                    {[...Array(6)].map((_, i) => (
                        <div key={i} style={{ width: '250px' }}>
                            <Skeleton height="180px" />
                            <Skeleton height="20px" width="60%" style={{ marginTop: '10px' }} />
                            <Skeleton height="20px" width="40%" />
                        </div>
                    ))}
                </div>
            </>
        );
    }

    if (error) return <div style={{ padding: '20px', color: 'red' }}>Erreur : {error}</div>;

    return (
        <div style={{ padding: '40px 20px', maxWidth: '1200px', margin: '0 auto', fontFamily: 'Arial, sans-serif' }}>
            <Helmet>
                <title>{pageTitle}</title>
                <meta name="description" content={pageDescription} />
            </Helmet>

            <h1 style={{ color: '#5F4C42', marginBottom: '30px', textAlign: 'center' }}>
                Catalogue VOLO
            </h1>

            <div style={{
                display: 'flex',
                gap: '30px',
                flexWrap: 'wrap',
                justifyContent: 'center'
            }}>
                {products.map((product) => (
                    <ProductCard
                        key={product.id}
                        product={product}
                        onAddToCart={addToCart}
                    />
                ))}
                {products.length === 0 && !loading && (
                    <p style={{ width: '100%', textAlign: 'center', color: '#888' }}>
                        Aucun produit ne correspond a cette recherche.
                    </p>
                )}

            </div>
        </div>
    );
};

export default ProductListPage;
