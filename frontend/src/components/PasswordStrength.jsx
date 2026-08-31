/*
===============================================================================
Composant : PasswordStrength
===============================================================================
Objectif :
    Indicateur visuel de la force du mot de passe (barre + label).

Props :
    - password (string) : Mot de passe a evaluer.

Niveaux :
    - 0 criteres : vide (gris)
    - 1 critere  : Faible (rouge)
    - 2 criteres : Moyen (orange)
    - 3 criteres : Fort (vert)
===============================================================================
*/

const levels = [
    { label: '', color: '#ddd', width: '0%' },
    { label: 'Faible', color: '#d9534f', width: '33%' },
    { label: 'Moyen', color: '#f0ad4e', width: '66%' },
    { label: 'Fort', color: '#4CAF50', width: '100%' },
];

const getStrength = (password) => {
    if (!password) return 0;
    let score = 0;
    if (password.length >= 8) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[!@#$%^&*(),.?":{}|<>_\-+=~`[\]\\/;']/.test(password)) score++;
    return score;
};

const barTrackStyle = {
    height: '4px',
    backgroundColor: '#E9D7C3',
    borderRadius: '2px',
    marginTop: '8px',
    overflow: 'hidden',
};

const PasswordStrength = ({ password }) => {
    const score = getStrength(password);
    const level = levels[score];

    if (!password) return null;

    return (
        <div style={{ marginTop: '-10px', marginBottom: '10px' }}>
            <div style={barTrackStyle}>
                <div style={{
                    height: '100%',
                    width: level.width,
                    backgroundColor: level.color,
                    borderRadius: '2px',
                    transition: 'width 0.3s ease, background-color 0.3s ease',
                }} />
            </div>
            <span style={{
                fontSize: '0.75em',
                color: level.color,
                fontWeight: 600,
            }}>
                {level.label}
            </span>
        </div>
    );
};

export default PasswordStrength;
