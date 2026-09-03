/*
===============================================================================
Module API : Produits
===============================================================================
Objectif :
    Fournir des methodes specifiques pour manipuler les donnees Produits
    issues de l'API Symfony.

Responsabilites :
    - Recuperer la liste des produits avec filtres optionnels.
    - Recuperer le detail d'un produit par son identifiant.
    - Transformer un objet de parametres JavaScript en QueryString URL.
    - Deballe la reponse API pour retourner uniquement les donnees utiles.

Exemple d'utilisation :
    const products = await fetchProducts({ brand: 1, page: 1 });
    // products.data -> Liste des produits

    const product = await fetchProductById(42);
    // product.data -> Detail du produit
===============================================================================
*/

import { apiCall } from './api';

/**
 * Recupere une liste paginee de produits, avec filtres optionnels.
 *
 * @param {object} params Filtres (brand, skin_concern, page, limit, available).
 * @returns {Promise<{data: Array, meta: object}>}
 */
export const fetchProducts = async (params = {}) => {
    const queryString = new URLSearchParams(params).toString();
    const endpoint = `/products${queryString ? '?' + queryString : ''}`;

    const data = await apiCall(endpoint);

    return data;
};

/**
 * Recupere le detail complet d'un produit (marque, problematiques, routines).
 *
 * @param {number|string} id Identifiant du produit.
 * @returns {Promise<{data: object}>}
 */
export const fetchProductById = async (id) => {
    const data = await apiCall(`/products/${id}`);

    return data;
};
