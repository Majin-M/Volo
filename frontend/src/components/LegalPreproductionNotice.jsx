/*
===============================================================================
Composant : LegalPreproductionNotice
===============================================================================
Objectif :
    Prevenir, en tete des pages legales, que les informations d'identification
    affichees sont fictives tant que le site est en pre-production.

    Sans ce bandeau, un visiteur — ou un jury — lirait un SIRET et une adresse
    sans pouvoir savoir qu'ils sont inventes. Il s'affiche uniquement si
    INFORMATIONS_FICTIVES vaut true dans src/config/legalIdentity.js, et
    disparait donc de lui-meme quand les vraies informations sont en place.
===============================================================================
*/

import { INFORMATIONS_FICTIVES } from '../config/legalIdentity';
import styles from '../pages/LegalPage.module.css';

const LegalPreproductionNotice = () => {
    if (!INFORMATIONS_FICTIVES) {
        return null;
    }

    return (
        <p className={styles.notice} role="note">
            <strong>Site en pre-production :</strong> les informations d'identification de
            cette page (societe, SIRET, adresse, telephone, hebergeur, mediateur) sont
            fictives. Elles seront remplacees par les informations reelles avant la mise en
            ligne.
        </p>
    );
};

export default LegalPreproductionNotice;
