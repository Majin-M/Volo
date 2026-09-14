/*
===============================================================================
Configuration : identite legale du site — SOURCE UNIQUE
===============================================================================
Objectif :
    Regrouper en un seul endroit toutes les informations d'identification
    affichees par les pages legales (mentions legales, CGV, politique de
    confidentialite), le pied de page et la page contact.

    Auparavant, ces informations etaient ecrites en dur dans cinq fichiers,
    en partie « (a completer) » ou entre crochets. Mettre le site en ligne
    aurait impose de les retrouver une a une, avec le risque d'en oublier.

PRE-PRODUCTION — VALEURS FICTIVES :
    Elles sont choisies pour NE POUVOIR DESIGNER AUCUNE ENTITE REELLE :
    - domaine en .example : reserve par la RFC 2606, personne ne peut
      l'enregistrer, et aucun email n'y est delivrable ;
    - code postal 00000 et ville « Exempleville » : n'existent pas ;
    - SIRET, RCS, TVA et telephones composes de zeros : jamais attribues.
    L'ancienne adresse, « 12 rue de la Paix, 75001 Paris », existe
    reellement : elle a ete retiree.

AVANT LA MISE EN PRODUCTION :
    1. Remplacer toutes les valeurs par les informations reelles.
    2. Passer INFORMATIONS_FICTIVES a false : le bandeau d'avertissement
       disparait des pages legales.
    Les deux doivent aller ensemble — des vraies valeurs sous un bandeau
    « fictif », ou l'inverse, seraient trompeuses.
===============================================================================
*/

/** Affiche un bandeau « informations fictives » sur les pages legales. */
export const INFORMATIONS_FICTIVES = true;

export const SITE = {
    domaine: 'volo.example',
};

export const EDITEUR = {
    raisonSociale: 'VOLO SAS',
    formeJuridique: 'Societe par Actions Simplifiee',
    capital: '10 000 euros',
    siege: "1 rue de l'Exemple, 00000 Exempleville, France",
    villeRcs: 'Exempleville',
    siret: '000 000 000 00000',
    numeroRcs: '000 000 000',
    tva: 'FR 00 000000000',
    directeurPublication: 'Camille Exemple',
    email: 'contact@volo.example',
    emailDpo: 'dpo@volo.example',
    telephone: '00 00 00 00 00',
};

export const HEBERGEUR = {
    nom: 'Hebergeur Exemple',
    raisonSociale: 'Hebergeur Exemple SAS',
    adresse: "2 avenue de l'Exemple, 00000 Exempleville, France",
    telephone: '00 00 00 00 00',
    siteWeb: 'https://hebergeur.example',
};

export const MEDIATEUR = {
    nom: 'Mediateur Exemple de la consommation',
    adresse: "3 place de l'Exemple, 00000 Exempleville, France",
    siteWeb: 'https://mediateur.example',
};

export const CREDITS = {
    developpement: 'Equipe VOLO — projet de formation',
    design: 'Equipe VOLO',
    photographies: 'Visuels de demonstration',
    icones: 'Icones SVG dessinees pour le projet',
};
