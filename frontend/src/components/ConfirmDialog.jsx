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
    const dialogRef = useRef(null);
    const onCancelRef = useRef(onCancel);

    useEffect(() => {
        onCancelRef.current = onCancel;
    });

    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;

        if (open && !dialog.open) {
            dialog.showModal();
        } else if (!open && dialog.open) {
            dialog.close();
        }
    }, [open]);

    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;

        const handleCancel = (e) => {
            e.preventDefault();
            onCancelRef.current?.();
        };

        const handleClick = (e) => {
            if (e.target === dialog) {
                onCancelRef.current?.();
            }
        };

        dialog.addEventListener('cancel', handleCancel);
        dialog.addEventListener('click', handleClick);
        return () => {
            dialog.removeEventListener('cancel', handleCancel);
            dialog.removeEventListener('click', handleClick);
        };
    }, []);

    const iconColor = danger ? '#c62828' : '#5F4C42';
    const iconBg = danger ? '#fde8e8' : '#F8F0E8';

    return (
        <>
            <style>{`
                .volo-confirm-dialog {
                    background: #fff;
                    border-radius: 20px;
                    padding: 0;
                    max-width: 420px;
                    width: 90%;
                    border: none;
                    box-shadow: 0 24px 64px rgba(0,0,0,0.15), 0 0 0 1px rgba(233,215,195,0.3);
                    overflow: hidden;
                    animation: volo-dialog-in 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
                }
                .volo-confirm-dialog::backdrop {
                    background: rgba(95, 76, 66, 0.35);
                    backdrop-filter: blur(4px);
                    -webkit-backdrop-filter: blur(4px);
                    animation: volo-backdrop-in 0.25s ease;
                }
                @keyframes volo-dialog-in {
                    from {
                        transform: scale(0.9) translateY(20px);
                        opacity: 0;
                    }
                    to {
                        transform: scale(1) translateY(0);
                        opacity: 1;
                    }
                }
                @keyframes volo-backdrop-in {
                    from { opacity: 0; }
                    to   { opacity: 1; }
                }
                .volo-confirm-cancel:hover {
                    background-color: #F8F0E8 !important;
                }
                .volo-confirm-action:hover {
                    filter: brightness(1.1);
                    transform: translateY(-1px);
                }
                .volo-confirm-action:active {
                    transform: translateY(0);
                }
            `}</style>
            <dialog
                ref={dialogRef}
                className="volo-confirm-dialog"
                aria-labelledby="confirm-dialog-title"
            >
                <div style={{ padding: '32px 32px 28px' }}>
                    <div style={{
                        width: '56px',
                        height: '56px',
                        borderRadius: '16px',
                        backgroundColor: iconBg,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        marginBottom: '20px',
                    }}>
                        {danger ? (
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke={iconColor} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                <line x1="12" y1="9" x2="12" y2="13" />
                                <line x1="12" y1="17" x2="12.01" y2="17" />
                            </svg>
                        ) : (
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke={iconColor} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M12 8h.01M11 12h1v4h1" />
                            </svg>
                        )}
                    </div>
                    <h2
                        id="confirm-dialog-title"
                        style={{
                            fontFamily: "'Playfair Display', serif",
                            fontSize: '1.35em',
                            color: '#5F4C42',
                            margin: '0 0 8px',
                            lineHeight: 1.3,
                        }}
                    >
                        {title}
                    </h2>
                    <p style={{
                        color: '#7a6a60',
                        fontSize: '0.95em',
                        lineHeight: 1.6,
                        margin: 0,
                    }}>
                        {message}
                    </p>
                </div>

                <div style={{
                    display: 'flex',
                    gap: '10px',
                    padding: '0 32px 28px',
                    justifyContent: 'flex-end',
                }}>
                    <button
                        type="button"
                        className="volo-confirm-cancel"
                        style={{
                            padding: '11px 24px',
                            backgroundColor: 'transparent',
                            color: '#5F4C42',
                            border: '1px solid #E9D7C3',
                            borderRadius: '12px',
                            fontSize: '0.95em',
                            cursor: 'pointer',
                            fontWeight: 500,
                            fontFamily: "'Lato', sans-serif",
                            transition: 'background-color 0.2s ease',
                        }}
                        onClick={onCancel}
                    >
                        {cancelLabel}
                    </button>
                    <button
                        type="button"
                        className="volo-confirm-action"
                        style={{
                            padding: '11px 24px',
                            color: '#fff',
                            border: 'none',
                            borderRadius: '12px',
                            fontSize: '0.95em',
                            cursor: 'pointer',
                            fontWeight: 600,
                            fontFamily: "'Lato', sans-serif",
                            background: danger
                                ? 'linear-gradient(135deg, #c62828, #e53935)'
                                : 'linear-gradient(135deg, #5F4C42, #7a6a60)',
                            boxShadow: danger
                                ? '0 4px 14px rgba(198,40,40,0.3)'
                                : '0 4px 14px rgba(95,76,66,0.3)',
                            transition: 'filter 0.2s ease, transform 0.15s ease',
                        }}
                        onClick={onConfirm}
                        autoFocus
                    >
                        {confirmLabel}
                    </button>
                </div>
            </dialog>
        </>
    );
};

export default ConfirmDialog;
