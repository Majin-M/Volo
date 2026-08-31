/*
===============================================================================
Contexte : ToastContext
===============================================================================
Objectif :
    Systeme de notifications ephemeres (toasts) accessible depuis n'importe
    quel composant via le hook useToast().

Types supportes :
    - success : Action reussie (vert).
    - error   : Erreur (rouge).
    - warning : Avertissement (orange).
    - info    : Information neutre (bleu).

Utilisation :
    const { addToast } = useToast();
    addToast('Produit ajoute au panier', 'success');
===============================================================================
*/

import { createContext, useContext, useState, useCallback, useRef } from 'react';

const ToastContext = createContext(null);

let toastId = 0;

const TOAST_DURATION = 4000;

const containerStyle = {
    position: 'fixed',
    bottom: '24px',
    right: '24px',
    zIndex: 9999,
    display: 'flex',
    flexDirection: 'column-reverse',
    gap: '10px',
    pointerEvents: 'none',
};

const baseToastStyle = {
    pointerEvents: 'auto',
    display: 'flex',
    alignItems: 'center',
    gap: '10px',
    padding: '14px 20px',
    borderRadius: '10px',
    fontFamily: "'Lato', sans-serif",
    fontSize: '0.95em',
    fontWeight: 500,
    color: '#fff',
    boxShadow: '0 6px 20px rgba(0,0,0,0.15)',
    animation: 'toastSlideIn 0.3s ease',
    maxWidth: '380px',
    lineHeight: 1.4,
};

const typeStyles = {
    success: { backgroundColor: '#4CAF50' },
    error: { backgroundColor: '#d9534f' },
    warning: { backgroundColor: '#f0ad4e', color: '#333' },
    info: { backgroundColor: '#5F4C42' },
};

const typeIcons = {
    success: '\u2713',
    error: '\u2717',
    warning: '\u26A0',
    info: '\u2139',
};

const closeButtonStyle = {
    background: 'none',
    border: 'none',
    color: 'inherit',
    fontSize: '1.2em',
    cursor: 'pointer',
    marginLeft: 'auto',
    padding: '0 0 0 10px',
    opacity: 0.7,
    lineHeight: 1,
};

export const ToastProvider = ({ children }) => {
    const [toasts, setToasts] = useState([]);
    const timersRef = useRef({});

    const removeToast = useCallback((id) => {
        clearTimeout(timersRef.current[id]);
        delete timersRef.current[id];
        setToasts((prev) => prev.filter((t) => t.id !== id));
    }, []);

    const addToast = useCallback((message, type = 'info', duration = TOAST_DURATION) => {
        const id = ++toastId;
        setToasts((prev) => [...prev, { id, message, type }]);
        timersRef.current[id] = setTimeout(() => removeToast(id), duration);
        return id;
    }, [removeToast]);

    return (
        <ToastContext.Provider value={{ addToast, removeToast }}>
            {children}

            {/* Injection du keyframe une seule fois */}
            <style>{`
                @keyframes toastSlideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to   { transform: translateX(0);    opacity: 1; }
                }
            `}</style>

            {toasts.length > 0 && (
                <div style={containerStyle} aria-live="polite">
                    {toasts.map((toast) => (
                        <div
                            key={toast.id}
                            role="status"
                            style={{ ...baseToastStyle, ...typeStyles[toast.type] }}
                        >
                            <span aria-hidden="true" style={{ fontSize: '1.2em' }}>
                                {typeIcons[toast.type]}
                            </span>
                            <span>{toast.message}</span>
                            <button
                                type="button"
                                style={closeButtonStyle}
                                onClick={() => removeToast(toast.id)}
                                aria-label="Fermer"
                            >
                                &times;
                            </button>
                        </div>
                    ))}
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
