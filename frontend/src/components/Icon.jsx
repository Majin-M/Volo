/*
===============================================================================
Composant : Icon
===============================================================================
Objectif :
    Icones du site, dessinees en SVG au trait, a la place des emojis.

Pourquoi pas des emojis :
    Leur rendu change selon le systeme (Windows, macOS, Android), leurs
    couleurs ignorent la charte VOLO, et ils donnent un aspect generique.
    Un SVG au trait prend la couleur du texte (currentColor) : il suit la
    palette du site partout ou il est place.

Props :
    - name (string)  : Nom de l'icone (voir PATHS).
    - size (number)  : Taille en pixels (defaut 20).
    - className      : Classe CSS optionnelle.

Accessibilite :
    Decorative par defaut (aria-hidden). Le texte voisin porte le sens ; un
    bouton qui ne contient qu'une icone doit avoir son propre aria-label.
===============================================================================
*/

const PATHS = {
    droplet: <path d="M12 2.7s-6 6.6-6 11.3a6 6 0 0 0 12 0c0-4.7-6-11.3-6-11.3z" />,
    moon: <path d="M20.5 14.2A8.5 8.5 0 1 1 9.8 3.5a7 7 0 0 0 10.7 10.7z" />,
    sun: (
        <>
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
        </>
    ),
    leaf: (
        <>
            <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8 0 5.5-4.8 10-10 10z" />
            <path d="M2 21c0-3 1.9-5.4 5.1-6.1 2.4-.5 4.9-2 5.9-3.9" />
        </>
    ),
    mapPin: (
        <>
            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z" />
            <circle cx="12" cy="10" r="3" />
        </>
    ),
    mail: (
        <>
            <rect x="2" y="4" width="20" height="16" rx="2" />
            <path d="M22 7l-10 6L2 7" />
        </>
    ),
    clock: (
        <>
            <circle cx="12" cy="12" r="10" />
            <path d="M12 6v6l4 2" />
        </>
    ),
    lock: (
        <>
            <rect x="4" y="11" width="16" height="10" rx="2" />
            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
        </>
    ),
    check: <path d="M20 6L9 17l-5-5" />,
    close: <path d="M18 6L6 18M6 6l12 12" />,
    alert: (
        <>
            <circle cx="12" cy="12" r="10" />
            <path d="M12 8v4M12 16h.01" />
        </>
    ),
    info: (
        <>
            <circle cx="12" cy="12" r="10" />
            <path d="M12 16v-4M12 8h.01" />
        </>
    ),
};

const Icon = ({ name, size = 20, className }) => (
    <svg
        width={size}
        height={size}
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinecap="round"
        strokeLinejoin="round"
        className={className}
        aria-hidden="true"
        focusable="false"
    >
        {PATHS[name]}
    </svg>
);

export default Icon;
