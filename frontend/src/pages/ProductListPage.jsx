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

import { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { fetchProducts } from '../api/productApi';
import { useCart } from '../contexts/CartContext';
import { useToast } from '../contexts/ToastContext';
import ProductCard from '../components/ProductCard';
import Skeleton from '../components/Skeleton';

const ProductListPage = () => {
    const [products, setProducts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const { addToCart } = useCart();
    const { addToast } = useToast();
    const [searchParams] = useSearchParams();
    const concernSlug = searchParams.get('skin_concern');

    useEffect(() => {
        let cancelled = false;

        const loadProducts = async () => {
            try {
                const slugFromUrl = searchParams.get('skin_concern');
                const params = {};
                if (slugFromUrl) {
                    params.skin_concern = slugFromUrl;
                }

                const response = await fetchProducts(params);
                if (!cancelled) {
                    setProducts(response.data);
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

        loadProducts();
        return () => { cancelled = true; };
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
                <meta property="og:title" content={pageTitle} />
                <meta property="og:description" content={pageDescription} />
                <meta property="og:type" content="website" />
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
                        onAddToCart={(p) => {
                            addToCart(p);
                            addToast(`${p.name} ajoute au panier`, 'success');
                        }}
                    />
                ))}
                {products.length === 0 && !loading && (
                    <div style={{ width: '100%', textAlign: 'center', padding: '40px 0' }}>
                        <svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style={{ marginBottom: '15px', opacity: 0.6 }}>
                            <circle cx="42" cy="42" r="28" stroke="#5F4C42" strokeWidth="3" fill="#F8F0E8" />
                            <line x1="62" y1="62" x2="82" y2="82" stroke="#5F4C42" strokeWidth="3" strokeLinecap="round" />
                            <line x1="32" y1="42" x2="52" y2="42" stroke="#E9D7C3" strokeWidth="2" strokeLinecap="round" />
                        </svg>
                        <p style={{ color: '#888', fontSize: '1.1em', margin: '0 0 8px 0' }}>
                            Aucun produit ne correspond a cette recherche.
                        </p>
                        <p style={{ color: '#aaa', fontSize: '0.9em' }}>
                            Essayez avec d'autres filtres ou explorez tout le catalogue.
                        </p>
                    </div>
                )}

            </div>
        </div>
    );
};

export default ProductListPage;
