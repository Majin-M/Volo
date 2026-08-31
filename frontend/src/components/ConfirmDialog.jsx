/*
===============================================================================
Composant : ConfirmDialog
===============================================================================
Objectif :
    Modale de confirmation pour les actions destructives ou irreversibles.

Props :
    - open (boolean)         : Affiche ou masque la modale.
    - title (string)         : Titre de la modale.
    - message (string)       : Description de l'action.
    - confirmLabel (string)  : Texte du bouton de confirmation (defaut: "Confirmer").
    - cancelLabel (string)   : Texte du bouton d'annulation (defaut: "Annuler").
    - onConfirm (function)   : Callback si l'utilisateur confirme.
    - onCancel (function)    : Callback si l'utilisateur annule.
    - danger (boolean)       : Style rouge pour le bouton de confirmation.

Exemple :
    <ConfirmDialog
        open={showConfirm}
        title="Retirer du panier ?"
        message="Cet article sera supprime de votre panier."
        onConfirm={handleRemove}
        onCancel={() => setShowConfirm(false)}
        danger
    />
===============================================================================
*/

import { useEffect, useRef } from 'react';

const overlayStyle = {
    position: 'fixed',
    inset: 0,
    backgroundColor: 'rgba(0,0,0,0.4)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 10000,
    animation: 'confirmFadeIn 0.2s ease',
};

const dialogStyle = {
    backgroundColor: '#fff',
    borderRadius: '12px',
    padding: '30px',
    maxWidth: '400px',
    width: '90%',
    boxShadow: '0 10px 40px rgba(0,0,0,0.2)',
    animation: 'confirmSlideUp 0.2s ease',
};

const titleStyle = {
    fontFamily: "'Playfair Display', serif",
    fontSize: '1.3em',
    color: '#5F4C42',
    margin: '0 0 10px 0',
};

const messageStyle = {
    color: '#666',
    fontSize: '0.95em',
    lineHeight: 1.5,
    margin: '0 0 25px 0',
};

const actionsStyle = {
    display: 'flex',
    gap: '12px',
    justifyContent: 'flex-end',
};

const cancelBtnStyle = {
    padding: '10px 24px',
    backgroundColor: 'transparent',
    color: '#5F4C42',
    border: '1px solid #E9D7C3',
    borderRadius: '8px',
    fontSize: '0.95em',
    cursor: 'pointer',
    fontWeight: 500,
    transition: 'background-color 0.2s ease',
};

const confirmBtnBase = {
    padding: '10px 24px',
    color: '#fff',
    border: 'none',
    borderRadius: '8px',
    fontSize: '0.95em',
    cursor: 'pointer',
    fontWeight: 600,
    transition: 'transform 0.2s ease, background-color 0.2s ease',
};

const ConfirmDialog = ({
    open,
    title = 'Confirmation',
    message,
    confirmLabel = 'Confirmer',
    cancelLabel = 'Annuler',
    onConfirm,
    onCancel,
    danger = false,
}) => {
    const confirmRef = useRef(null);

    useEffect(() => {
        if (open && confirmRef.current) {
            confirmRef.current.focus();
        }
    }, [open]);

    // Fermer avec Escape
    useEffect(() => {
        if (!open) return;
        const handleKey = (e) => {
            if (e.key === 'Escape') onCancel?.();
        };
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [open, onCancel]);

    if (!open) return null;

    const confirmBtnStyle = {
        ...confirmBtnBase,
        backgroundColor: danger ? '#d9534f' : '#5F4C42',
    };

    return (
        <>
            <style>{`
                @keyframes confirmFadeIn {
                    from { opacity: 0; }
                    to   { opacity: 1; }
                }
                @keyframes confirmSlideUp {
                    from { transform: translateY(20px); opacity: 0; }
                    to   { transform: translateY(0);    opacity: 1; }
                }
            `}</style>
            <div
                style={overlayStyle}
                onClick={onCancel}
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-dialog-title"
            >
                <div style={dialogStyle} onClick={(e) => e.stopPropagation()}>
                    <h2 id="confirm-dialog-title" style={titleStyle}>{title}</h2>
                    <p style={messageStyle}>{message}</p>
                    <div style={actionsStyle}>
                        <button type="button" style={cancelBtnStyle} onClick={onCancel}>
                            {cancelLabel}
                        </button>
                        <button
                            type="button"
                            ref={confirmRef}
                            style={confirmBtnStyle}
                            onClick={onConfirm}
                        >
                            {confirmLabel}
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
};

export default ConfirmDialog;
