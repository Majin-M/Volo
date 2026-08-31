/*
===============================================================================
Composant : FormField
===============================================================================
Objectif :
    Champ de formulaire avec validation temps reel et feedback visuel.

Responsabilites :
    - Afficher le label, l'input et le message d'erreur inline.
    - Valider au blur (perte de focus) et au changement apres premier blur.
    - Afficher une bordure rouge + message sous le champ en cas d'erreur.
    - Afficher une bordure verte + coche si le champ est valide apres edition.

Props :
    - label (string)         : Texte du label.
    - id (string)            : ID du champ (lie au label via htmlFor).
    - type (string)          : Type de l'input (text, email, password...).
    - value (string)         : Valeur controlée.
    - onChange (function)     : Callback de changement.
    - validate (function)    : Renvoie string d'erreur ou null si valide.
    - placeholder (string)   : Texte indicatif.
    - required (boolean)     : Champ requis.
    - autoComplete (string)  : Attribut autocomplete.
    - className (string)     : Classe CSS pour l'input.
    - minLength (number)     : Longueur minimale.

Exemple :
    <FormField
        label="Email"
        id="email"
        type="email"
        value={email}
        onChange={setEmail}
        validate={(v) => !validateEmail(v) ? "Email invalide" : null}
    />
===============================================================================
*/

import { useState, useCallback } from 'react';

const errorMsgStyle = {
    color: '#d9534f',
    fontSize: '0.8em',
    marginTop: '5px',
    minHeight: '1.2em',
};

const validIconStyle = {
    position: 'absolute',
    right: '12px',
    top: '50%',
    transform: 'translateY(-50%)',
    color: '#4CAF50',
    fontSize: '1.1em',
    pointerEvents: 'none',
};

const FormField = ({
    label,
    id,
    type = 'text',
    value,
    onChange,
    validate,
    placeholder,
    required,
    autoComplete,
    className,
    minLength,
}) => {
    const [touched, setTouched] = useState(false);
    const [error, setError] = useState(null);

    const runValidation = useCallback((val) => {
        if (!validate) return null;
        const err = validate(val);
        setError(err);
        return err;
    }, [validate]);

    const handleBlur = () => {
        setTouched(true);
        runValidation(value);
    };

    const handleChange = (e) => {
        const newVal = e.target.value;
        onChange(newVal);
        if (touched) {
            runValidation(newVal);
        }
    };

    const isValid = touched && !error && value.length > 0;
    const isInvalid = touched && !!error;

    const borderColor = isInvalid ? '#d9534f' : isValid ? '#4CAF50' : undefined;
    const boxShadow = isInvalid
        ? '0 0 0 3px rgba(217,83,79,0.12)'
        : isValid
        ? '0 0 0 3px rgba(76,175,80,0.12)'
        : undefined;

    const inputStyle = borderColor
        ? { borderColor, boxShadow }
        : {};

    return (
        <div style={{ marginBottom: '18px', textAlign: 'left' }}>
            {label && (
                <label htmlFor={id} style={{
                    display: 'block',
                    color: '#5F4C42',
                    fontWeight: 600,
                    fontSize: '0.8em',
                    textTransform: 'uppercase',
                    letterSpacing: '0.5px',
                    marginBottom: '8px',
                }}>
                    {label}
                </label>
            )}
            <div style={{ position: 'relative' }}>
                <input
                    id={id}
                    type={type}
                    value={value}
                    onChange={handleChange}
                    onBlur={handleBlur}
                    placeholder={placeholder}
                    required={required}
                    autoComplete={autoComplete}
                    minLength={minLength}
                    className={className}
                    style={inputStyle}
                    aria-invalid={isInvalid || undefined}
                    aria-describedby={isInvalid ? `${id}-error` : undefined}
                />
                {isValid && <span style={validIconStyle} aria-hidden="true">&#10003;</span>}
            </div>
            <div id={`${id}-error`} style={errorMsgStyle} role={isInvalid ? 'alert' : undefined}>
                {isInvalid ? error : ''}
            </div>
        </div>
    );
};

export default FormField;
