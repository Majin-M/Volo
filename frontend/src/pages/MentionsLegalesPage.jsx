/*
===============================================================================
Page : MentionsLegalesPage
===============================================================================
Objectif :
    Afficher les mentions legales obligatoires au titre de la loi LCEN
    (Loi pour la Confiance dans l'Economie Numerique, art. 6-III).

Responsabilites :
    - Identifier l'editeur du site (raison sociale, siege, contact).
    - Identifier l'hebergeur.
    - Rappeler la propriete intellectuelle et les credits.

Exemple d'utilisation :
    <Route path="/mentions-legales" element={<MentionsLegalesPage />} />
===============================================================================
*/

import { Helmet } from 'react-helmet-async';
import styles from './LegalPage.module.css';

const MentionsLegalesPage = () => (
    <div className={styles.container}>
        <Helmet>
            <title>Mentions legales — VOLO</title>
            <meta name="description" content="Mentions legales du site VOLO : editeur, hebergeur, propriete intellectuelle." />
        </Helmet>

        <h1 className={styles.pageTitle}>Mentions legales</h1>
        <p className={styles.lastUpdated}>Derniere mise a jour : 31 aout 2026</p>

        <div className={styles.content}>
            <h2>1. Editeur du site</h2>
            <p>
                Le site <strong>volo-skin.fr</strong> est edite par :
            </p>
            <ul>
                <li><strong>Raison sociale :</strong> VOLO SAS</li>
                <li><strong>Siege social :</strong> 12 rue de la Paix, 75001 Paris, France</li>
                <li><strong>SIRET :</strong> 123 456 789 00001 (a completer)</li>
                <li><strong>RCS :</strong> Paris B 123 456 789 (a completer)</li>
                <li><strong>Capital social :</strong> 10 000 euros</li>
                <li><strong>Directeur de la publication :</strong> [Nom du responsable]</li>
                <li><strong>Email :</strong> <a href="mailto:contact@volo-skin.fr">contact@volo-skin.fr</a></li>
                <li><strong>Telephone :</strong> [Numero de telephone]</li>
                <li><strong>Numero de TVA intracommunautaire :</strong> FR XX 123456789 (a completer)</li>
            </ul>

            <h2>2. Hebergeur</h2>
            <p>Le site est heberge par :</p>
            <ul>
                <li><strong>Nom :</strong> [Nom de l'hebergeur]</li>
                <li><strong>Adresse :</strong> [Adresse de l'hebergeur]</li>
                <li><strong>Telephone :</strong> [Telephone de l'hebergeur]</li>
            </ul>

            <h2>3. Propriete intellectuelle</h2>
            <p>
                L'ensemble des contenus presents sur le site volo-skin.fr (textes, images,
                photographies, logos, marques, elements graphiques) est protege par le droit
                de la propriete intellectuelle et appartient a VOLO SAS ou fait l'objet d'une
                autorisation d'utilisation.
            </p>
            <p>
                Toute reproduction, representation, modification, publication ou adaptation de
                tout ou partie des elements du site, quel que soit le moyen ou le procede
                utilise, est interdite sans l'autorisation ecrite prealable de VOLO SAS.
            </p>

            <h2>4. Responsabilite</h2>
            <p>
                VOLO SAS s'efforce de fournir sur le site des informations aussi precises que
                possible. Toutefois, elle ne pourra etre tenue responsable des oublis, des
                inexactitudes ou des carences dans la mise a jour, qu'elles soient de son fait
                ou du fait de tiers partenaires qui lui fournissent ces informations.
            </p>
            <p>
                L'utilisation des informations et contenus disponibles sur l'ensemble du site
                ne saurait en aucun cas engager la responsabilite de VOLO SAS.
            </p>

            <h2>5. Liens hypertextes</h2>
            <p>
                Le site volo-skin.fr peut contenir des liens hypertextes vers d'autres sites.
                VOLO SAS ne dispose d'aucun moyen de controle du contenu de ces sites tiers et
                n'assume aucune responsabilite quant a leur contenu.
            </p>

            <h2>6. Droit applicable</h2>
            <p>
                Les presentes mentions legales sont regies par le droit francais. En cas de
                litige, les tribunaux francais seront seuls competents.
            </p>
        </div>
    </div>
);

export default MentionsLegalesPage;
