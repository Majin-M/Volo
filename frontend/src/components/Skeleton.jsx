/*
===============================================================================
Composant : Skeleton
===============================================================================
Objectif :
    Afficher un état de chargement visuel pendant que les données sont récupérées.

Responsabilités :
    - Simuler l'apparence d'un contenu (Produit, Texte) avec un effet de pulsation.
    - Améliorer la perception de performance (Perceived Performance) de l'application.
    - Remplacer les "Loading..." textuels par des visuels dynamiques.

Utilisation :
    Utilisé dans les listes (Catalogue) ou les pages détaillées en attendant le retour API.

Note Technique :
    L'animation est gérée par CSS (keyframes pulse) et déclenchée via la classe CSS.
===============================================================================
*/

import React from 'react';

const Skeleton = ({ height = '100%', width = '100%', borderRadius = '4px', style }) => {
    // Style par défaut avec l'effet de pulsation
    const baseStyle = {
        height: height,
        width: width,
        borderRadius: borderRadius,
        backgroundColor: '#e0e0e0',
        animation: 'pulse 1.5s infinite', // Appel à l'animation CSS définie dans index.css
        ...style, // Permet d'écraser le style par défaut si besoin
    };

    return <div style={baseStyle} />;
};

export default Skeleton;