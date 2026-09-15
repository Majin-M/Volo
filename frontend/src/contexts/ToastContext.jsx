/*
===============================================================================
Contexte : ToastContext
===============================================================================
Objectif :
    Systeme de notifications ephemeres (toasts) accessible depuis n'importe
    quel composant via le hook useToast().

Types supportes :
    - success : Action reussie (vert sauge de la charte).
    - error   : Erreur (rouge attenue).
    - warning : Avertissement (ocre).
    - info    : Information neutre (brun VOLO).

Tous partagent la meme carte ivoire et le meme texte brun : seule la bande
laterale et l'icone changent de couleur, pour rester dans l'identite du site.

Utilisation :
    const { addToast } = useToast();
    addToast('Produit ajoute au panier', 'success');
===============================================================================
*/

import { createContext, useContext, useState, useCallback, useRef, useMemo } from 'react';
import Icon from '../components/Icon';

const ToastContext = createContext(null);

let toastId = 0;

const TOAST_DURATION = 4000;

// accent : bande laterale, icone et barre de progression.
const typeConfig = {
    success: { accent: '#7E9C79', icon: 'check' },
    error: { accent: '#B5534B', icon: 'alert' },
    warning: { accent: '#B8863B', icon: 'alert' },
    info: { accent: '#5F4C42', icon: 'info' },
};

export const ToastProvider = ({ children }) => {
    const [toasts, setToasts] = useState([]);
    const timersRef = useRef({});

    const removeToast = useCallback((id) => {
        setToasts((prev) =>
            prev.map((t) => (t.id === id ? { ...t, exiting: true } : t))
        );
        setTimeout(() => {
            clearTimeout(timersRef.current[id]);
            delete timersRef.current[id];
            setToasts((prev) => prev.filter((t) => t.id !== id));
        }, 300);
    }, []);

    const addToast = useCallback((message, type = 'info', duration = TOAST_DURATION) => {
        const id = ++toastId;
        setToasts((prev) => [...prev, { id, message, type, exiting: false }]);
        timersRef.current[id] = setTimeout(() => removeToast(id), duration);
        return id;
    }, [removeToast]);

    const value = useMemo(() => ({ addToast, removeToast }), [addToast, removeToast]);

    return (
        <ToastContext.Provider value={value}>
            {children}

            <style>{`
                @keyframes volo-toast-in {
                    from {
                        transform: translateY(8px);
                        opacity: 0;
                    }
                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }
                @media (prefers-reduced-motion: reduce) {
                    [data-volo-toast] { animation: none !important; }
                }
                @keyframes volo-toast-out {
                    to {
                        transform: translateX(120%);
                        opacity: 0;
                    }
                }
                @keyframes volo-toast-progress {
                    from { transform: scaleX(1); }
                    to   { transform: scaleX(0); }
                }
            `}</style>

            {toasts.length > 0 && (
                <div
                    style={{
                        position: 'fixed',
                        bottom: '28px',
                        right: '28px',
                        zIndex: 9999,
                        display: 'flex',
                        flexDirection: 'column-reverse',
                        gap: '12px',
                        pointerEvents: 'none',
                    }}
                    aria-live="polite"
                >
                    {toasts.map((toast) => {
                        const config = typeConfig[toast.type] || typeConfig.info;
                        return (
                            <div
                                key={toast.id}
                                role="status"
                                data-volo-toast=""
                                style={{
                                    pointerEvents: 'auto',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '12px',
                                    padding: '14px 16px 14px 18px',
                                    borderRadius: '10px',
                                    borderLeft: `4px solid ${config.accent}`,
                                    fontFamily: "'Lato', sans-serif",
                                    fontSize: '0.95em',
                                    color: '#5F4C42',
                                    background: '#FFFCF8',
                                    boxShadow: '0 6px 24px rgba(95,76,66,0.14), 0 0 0 1px #E9D7C3',
                                    maxWidth: '400px',
                                    lineHeight: 1.4,
                                    position: 'relative',
                                    overflow: 'hidden',
                                    animation: toast.exiting
                                        ? 'volo-toast-out 0.3s ease forwards'
                                        : 'volo-toast-in 0.35s ease-out',
                                }}
                            >
                                <span style={{ display: 'flex', color: config.accent, flexShrink: 0 }}>
                                    <Icon name={config.icon} size={20} />
                                </span>
                                <span style={{ flex: 1 }}>{toast.message}</span>
                                <button
                                    type="button"
                                    style={{
                                        display: 'flex',
                                        background: 'none',
                                        border: 'none',
                                        color: '#8a7a70',
                                        cursor: 'pointer',
                                        padding: '4px',
                                        marginLeft: '4px',
                                        borderRadius: '6px',
                                    }}
                                    onClick={() => removeToast(toast.id)}
                                    aria-label="Fermer"
                                >
                                    <Icon name="close" size={16} />
                                </button>
                                <div style={{
                                    position: 'absolute',
                                    bottom: 0,
                                    left: 0,
                                    right: 0,
                                    height: '2px',
                                    backgroundColor: config.accent,
                                    opacity: 0.35,
                                    transformOrigin: 'left',
                                    animation: `volo-toast-progress ${TOAST_DURATION}ms linear forwards`,
                                }} />
                            </div>
                        );
                    })}
                </div>
            )}
        </ToastContext.Provider>
    );
};

export const useToast = () => {
    const ctx = useContext(ToastContext);
    if (!ctx) throw new Error('useToast must be used within a ToastProvider');
    return ctx;
};
