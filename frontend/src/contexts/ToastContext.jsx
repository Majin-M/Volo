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
    - info    : Information neutre (brun VOLO).

Utilisation :
    const { addToast } = useToast();
    addToast('Produit ajoute au panier', 'success');
===============================================================================
*/

import { createContext, useContext, useState, useCallback, useRef, useMemo } from 'react';

const ToastContext = createContext(null);

let toastId = 0;

const TOAST_DURATION = 4000;

const typeConfig = {
    success: {
        bg: 'linear-gradient(135deg, #2e7d32, #43a047)',
        border: '#66bb6a',
        icon: (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10" opacity="0.3" />
                <path d="M8 12l3 3 5-6" />
            </svg>
        ),
    },
    error: {
        bg: 'linear-gradient(135deg, #c62828, #e53935)',
        border: '#ef5350',
        icon: (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10" opacity="0.3" />
                <path d="M15 9l-6 6M9 9l6 6" />
            </svg>
        ),
    },
    warning: {
        bg: 'linear-gradient(135deg, #e65100, #f57c00)',
        border: '#ffa726',
        icon: (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M12 9v4M12 17h.01" />
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" opacity="0.3" />
            </svg>
        ),
    },
    info: {
        bg: 'linear-gradient(135deg, #5F4C42, #7a6a60)',
        border: '#E9D7C3',
        icon: (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10" opacity="0.3" />
                <path d="M12 16v-4M12 8h.01" />
            </svg>
        ),
    },
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
                        transform: translateX(120%);
                        opacity: 0;
                    }
                    60% {
                        transform: translateX(-6px);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
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
                                style={{
                                    pointerEvents: 'auto',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '12px',
                                    padding: '16px 20px',
                                    borderRadius: '14px',
                                    fontFamily: "'Lato', sans-serif",
                                    fontSize: '0.95em',
                                    fontWeight: 500,
                                    color: '#fff',
                                    background: config.bg,
                                    boxShadow: `0 8px 32px rgba(0,0,0,0.18), 0 0 0 1px ${config.border}33`,
                                    maxWidth: '400px',
                                    lineHeight: 1.4,
                                    position: 'relative',
                                    overflow: 'hidden',
                                    backdropFilter: 'blur(8px)',
                                    animation: toast.exiting
                                        ? 'volo-toast-out 0.3s ease forwards'
                                        : 'volo-toast-in 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)',
                                }}
                            >
                                <span style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    width: '32px',
                                    height: '32px',
                                    borderRadius: '50%',
                                    backgroundColor: 'rgba(255,255,255,0.15)',
                                    flexShrink: 0,
                                }}>
                                    {config.icon}
                                </span>
                                <span style={{ flex: 1 }}>{toast.message}</span>
                                <button
                                    type="button"
                                    style={{
                                        background: 'none',
                                        border: 'none',
                                        color: 'rgba(255,255,255,0.6)',
                                        fontSize: '1.3em',
                                        cursor: 'pointer',
                                        padding: '0 0 0 8px',
                                        lineHeight: 1,
                                        transition: 'color 0.2s',
                                    }}
                                    onMouseEnter={(e) => { e.target.style.color = '#fff'; }}
                                    onMouseLeave={(e) => { e.target.style.color = 'rgba(255,255,255,0.6)'; }}
                                    onClick={() => removeToast(toast.id)}
                                    aria-label="Fermer"
                                >
                                    &times;
                                </button>
                                <div style={{
                                    position: 'absolute',
                                    bottom: 0,
                                    left: 0,
                                    right: 0,
                                    height: '3px',
                                    backgroundColor: 'rgba(255,255,255,0.3)',
                                    transformOrigin: 'left',
                                    animation: `volo-toast-progress ${TOAST_DURATION}ms linear forwards`,
                                    borderRadius: '0 0 14px 14px',
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
