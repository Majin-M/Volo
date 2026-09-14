/*
===============================================================================
Composant : PasswordInput
===============================================================================
Objectif :
    Champ mot de passe avec un bouton pour afficher ou masquer la saisie,
    comme sur la plupart des sites.

Pourquoi un composant dedie :
    Les six champs mot de passe du site (connexion, inscription, changement de
    mot de passe) etaient de simples <input type="password">. Impossible de
    verifier ce qu'on tape — genant avec les exigences de complexite de
    l'inscription, et source d'erreurs de confirmation. Un seul composant
    garantit le meme comportement partout.

Accessibilite :
    - Le bouton est type="button" : sans cela, le cliquer SOUMETTRAIT le
      formulaire qui l'entoure.
    - Son libelle change selon l'etat (« Afficher » / « Masquer ») et
      aria-pressed indique s'il est actif, pour les lecteurs d'ecran.
    - aria-controls le relie au champ qu'il pilote.

Props :
    - id (string)        : Obligatoire, relie le label et le bouton au champ.
    - className (string) : Classe CSS de l'input.
    - style (object)     : Styles de l'input (bordure de validation...).
    - ...inputProps      : Tous les autres attributs de l'input (value,
                           onChange, onBlur, autoComplete, required...).
===============================================================================
*/

import { useState } from 'react';
import styles from './PasswordInput.module.css';

const IconeOeil = () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
        <circle cx="12" cy="12" r="3" />
    </svg>
);

const IconeOeilBarre = () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" />
        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" />
        <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
        <line x1="1" y1="1" x2="23" y2="23" />
    </svg>
);

const PasswordInput = ({ id, className, style, ...inputProps }) => {
    const [visible, setVisible] = useState(false);

    return (
        <div className={styles.wrapper}>
            <input
                {...inputProps}
                id={id}
                type={visible ? 'text' : 'password'}
                className={className}
                // Reserve la place du bouton : sans cela, la fin de la saisie
                // passerait sous l'icone.
                style={{ ...style, paddingRight: '44px' }}
            />
            <button
                type="button"
                className={styles.toggle}
                onClick={() => setVisible((v) => !v)}
                aria-label={visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                aria-pressed={visible}
                aria-controls={id}
            >
                {visible ? <IconeOeilBarre /> : <IconeOeil />}
            </button>
        </div>
    );
};

export default PasswordInput;
