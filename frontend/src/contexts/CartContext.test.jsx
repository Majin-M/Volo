import { describe, it, expect, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { CartProvider, useCart } from './CartContext';

const product1 = { id: 1, name: 'Serum A', price: '29.90' };
const product2 = { id: 2, name: 'Creme B', price: '19.50' };

function renderCartHook() {
    return renderHook(() => useCart(), { wrapper: CartProvider });
}

describe('CartContext', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('demarre avec un panier vide', () => {
        const { result } = renderCartHook();
        expect(result.current.cartItems).toEqual([]);
        expect(result.current.cartCount).toBe(0);
        expect(result.current.cartTotal).toBe(0);
    });

    it('ajoute un produit au panier', () => {
        const { result } = renderCartHook();
        act(() => result.current.addToCart(product1));
        expect(result.current.cartItems).toHaveLength(1);
        expect(result.current.cartItems[0].name).toBe('Serum A');
        expect(result.current.cartItems[0].quantity).toBe(1);
    });

    it('incremente la quantite si le produit existe deja', () => {
        const { result } = renderCartHook();
        act(() => result.current.addToCart(product1));
        act(() => result.current.addToCart(product1));
        expect(result.current.cartItems).toHaveLength(1);
        expect(result.current.cartItems[0].quantity).toBe(2);
    });

    it('calcule le total correctement', () => {
        const { result } = renderCartHook();
        act(() => result.current.addToCart(product1));
        act(() => result.current.addToCart(product2));
        expect(result.current.cartTotal).toBeCloseTo(49.40);
        expect(result.current.cartCount).toBe(2);
    });

    it('retire un produit du panier', () => {
        const { result } = renderCartHook();
        act(() => result.current.addToCart(product1));
        act(() => result.current.addToCart(product2));
        act(() => result.current.removeFromCart(1));
        expect(result.current.cartItems).toHaveLength(1);
        expect(result.current.cartItems[0].id).toBe(2);
    });

    it('met a jour la quantite', () => {
        const { result } = renderCartHook();
        act(() => result.current.addToCart(product1));
        act(() => result.current.updateQuantity(1, 5));
        expect(result.current.cartItems[0].quantity).toBe(5);
        expect(result.current.cartTotal).toBeCloseTo(149.50);
    });

    it('retire le produit si la quantite passe sous 1', () => {
        const { result } = renderCartHook();
        act(() => result.current.addToCart(product1));
        act(() => result.current.updateQuantity(1, 0));
        expect(result.current.cartItems).toHaveLength(0);
    });

    it('vide le panier', () => {
        const { result } = renderCartHook();
        act(() => result.current.addToCart(product1));
        act(() => result.current.addToCart(product2));
        act(() => result.current.clearCart());
        expect(result.current.cartItems).toEqual([]);
        expect(result.current.cartCount).toBe(0);
    });

    it('persiste le panier dans localStorage', () => {
        const { result } = renderCartHook();
        act(() => result.current.addToCart(product1));
        const stored = JSON.parse(localStorage.getItem('volo_cart:v1'));
        expect(stored).toHaveLength(1);
        expect(stored[0].id).toBe(1);
    });

    it('restaure le panier depuis localStorage', () => {
        localStorage.setItem('volo_cart:v1', JSON.stringify([
            { ...product1, quantity: 3 },
        ]));
        const { result } = renderCartHook();
        expect(result.current.cartItems).toHaveLength(1);
        expect(result.current.cartItems[0].quantity).toBe(3);
    });
});
