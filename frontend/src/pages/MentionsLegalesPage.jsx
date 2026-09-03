/*
===============================================================================
Page : MentionsLegalesPage
===============================================================================
Objectif :
    Afficher les mentions legales obligatoires au titre de la loi LCEN
    (Loi pour la Confiance dans l'Economie Numerique, art. 6-III) et
    les informations requises pour un site de commerce electronique.

Responsabilites :
    - Identifier l'editeur du site (raison sociale, siege, contact).
    - Identifier l'hebergeur.
    - Rappeler la propriete intellectuelle et les credits.
    - Indiquer le contact DPO / RGPD.
    - Preciser les conditions d'utilisation du site.

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
            <meta name="description" content="Mentions legales du site VOLO : editeur, hebergeur, propriete intellectuelle, conditions d'utilisation." />
        </Helmet>

        <h1 className={styles.pageTitle}>Mentions legales</h1>
        <p className={styles.lastUpdated}>Derniere mise a jour : 2 septembre 2026</p>

        <div className={styles.content}>
            <h2>1. Editeur du site</h2>
            <p>
                Le site <strong>volo-skin.fr</strong> est edite par :
            </p>
            <ul>
                <li><strong>Raison sociale :</strong> VOLO SAS</li>
                <li><strong>Forme juridique :</strong> Societe par Actions Simplifiee</li>
                <li><strong>Siege social :</strong> 12 rue de la Paix, 75001 Paris, France</li>
                <li><strong>SIRET :</strong> 123 456 789 00001 (a completer)</li>
                <li><strong>RCS :</strong> Paris B 123 456 789 (a completer)</li>
                <li><strong>Capital social :</strong> 10 000 euros</li>
                <li><strong>Numero de TVA intracommunautaire :</strong> FR XX 123456789 (a completer)</li>
                <li><strong>Directeur de la publication :</strong> [Nom du responsable — a completer]</li>
                <li><strong>Email :</strong> <a href="mailto:contact@volo-skin.fr">contact@volo-skin.fr</a></li>
                <li><strong>Telephone :</strong> [Numero de telephone — a completer]</li>
            </ul>
            <p>
                VOLO SAS exerce une activite de vente en ligne de produits cosmetiques
                conformes au reglement (CE) n° 1223/2009 relatif aux produits cosmetiques.
            </p>

            <h2>2. Hebergeur</h2>
            <p>Le site est heberge par :</p>
            <ul>
                <li><strong>Nom :</strong> [Nom de l'hebergeur — a completer]</li>
                <li><strong>Raison sociale :</strong> [Raison sociale — a completer]</li>
                <li><strong>Adresse :</strong> [Adresse de l'hebergeur — a completer]</li>
                <li><strong>Telephone :</strong> [Telephone de l'hebergeur — a completer]</li>
                <li><strong>Site web :</strong> [URL de l'hebergeur — a completer]</li>
            </ul>

            <h2>3. Conditions d'utilisation du site</h2>
            <p>
                L'utilisation du site volo-skin.fr implique l'acceptation pleine et entiere
                des conditions d'utilisation ci-apres decrites. VOLO SAS se reserve le droit de
                modifier ces conditions a tout moment ; les modifications prennent effet des
                leur publication sur le site.
            </p>

            <h3>3.1 Acces au site</h3>
            <p>
                Le site est accessible gratuitement depuis tout lieu a tout utilisateur
                disposant d'un acces a Internet. Les frais d'acces au reseau et les
                equipements necessaires a la connexion sont a la charge de l'utilisateur.
            </p>
            <p>
                VOLO SAS met en oeuvre tous les moyens raisonnables pour assurer un acces de
                qualite au site mais n'est tenue a aucune obligation d'y parvenir. Le site peut
                etre interrompu a tout moment pour raison de maintenance, de mise a jour ou pour
                toute autre raison technique.
            </p>

            <h3>3.2 Compte utilisateur</h3>
            <p>
                Certaines fonctionnalites du site (commande, historique, espace personnel)
                necessitent la creation d'un compte. L'utilisateur s'engage a fournir des
                informations exactes et a jour, et a maintenir la confidentialite de ses
                identifiants de connexion.
            </p>
            <p>
                Toute utilisation du compte avec les identifiants de l'utilisateur est
                presumee emaner de ce dernier. En cas d'utilisation frauduleuse, l'utilisateur
                doit en informer immediatement VOLO SAS a l'adresse{' '}
                <a href="mailto:contact@volo-skin.fr">contact@volo-skin.fr</a>.
            </p>
            <p>
                VOLO SAS se reserve le droit de suspendre ou de supprimer tout compte en cas de
                manquement aux presentes conditions d'utilisation, d'activite frauduleuse ou de
                comportement portant atteinte aux interets de VOLO SAS ou de ses clients.
            </p>

            <h3>3.3 Comportements interdits</h3>
            <p>L'utilisateur s'interdit de :</p>
            <ul>
                <li>Utiliser le site a des fins illicites ou non autorisees.</li>
                <li>Tenter d'acceder de maniere non autorisee aux systemes informatiques du site.</li>
                <li>Transmettre des contenus illicites, diffamatoires, obscenes ou portant atteinte aux droits de tiers.</li>
                <li>Utiliser des dispositifs automatises (robots, scrapers) pour collecter des donnees du site.</li>
                <li>Passer des commandes frauduleuses ou sous une fausse identite.</li>
            </ul>

            <h2>4. Propriete intellectuelle</h2>
            <p>
                L'ensemble des contenus presents sur le site volo-skin.fr (textes, images,
                photographies, logos, marques, elements graphiques, logiciels, base de donnees,
                architecture du site) est protege par le droit de la propriete intellectuelle
                et appartient a VOLO SAS ou fait l'objet d'une autorisation d'utilisation.
            </p>
            <p>
                La marque VOLO et le logo associe sont des marques deposees. Toute reproduction
                ou utilisation non autorisee de ces marques est interdite.
            </p>
            <p>
                Toute reproduction, representation, modification, publication, transmission ou
                adaptation de tout ou partie des elements du site, quel que soit le moyen ou le
                procede utilise, est interdite sans l'autorisation ecrite prealable de VOLO SAS.
                Toute exploitation non autorisee constitue une contrefacon sanctionnee par les
                articles L335-2 et suivants du Code de la propriete intellectuelle.
            </p>

            <h2>5. Responsabilite</h2>
            <p>
                VOLO SAS s'efforce de fournir sur le site des informations aussi precises que
                possible. Toutefois, elle ne pourra etre tenue responsable des oublis, des
                inexactitudes ou des carences dans la mise a jour, qu'elles soient de son fait
                ou du fait de tiers partenaires qui lui fournissent ces informations.
            </p>
            <p>
                L'utilisation des informations et contenus disponibles sur l'ensemble du site
                ne saurait en aucun cas engager la responsabilite de VOLO SAS. L'utilisateur
                est seul responsable de l'utilisation qu'il fait du contenu du site.
            </p>

            <h2>6. Liens hypertextes</h2>
            <p>
                Le site volo-skin.fr peut contenir des liens hypertextes vers d'autres sites
                (notamment Stripe pour le paiement). VOLO SAS ne dispose d'aucun moyen de
                controle du contenu de ces sites tiers et n'assume aucune responsabilite quant
                a leur contenu ou aux eventuels traitements de donnees personnelles qu'ils
                operent.
            </p>
            <p>
                La mise en place de liens hypertextes vers le site volo-skin.fr necessite
                l'autorisation prealable et ecrite de VOLO SAS.
            </p>

            <h2>7. Protection des donnees personnelles</h2>
            <p>
                Conformement au Reglement General sur la Protection des Donnees (RGPD,
                UE 2016/679) et a la loi n° 78-17 du 6 janvier 1978 modifiee relative a
                l'informatique, aux fichiers et aux libertes, le traitement des donnees
                personnelles des utilisateurs est detaille dans notre{' '}
                <a href="/politique-confidentialite">politique de confidentialite</a>.
            </p>
            <p>
                <strong>Contact pour les questions relatives aux donnees personnelles :</strong>
            </p>
            <ul>
                <li><strong>Email :</strong> <a href="mailto:dpo@volo-skin.fr">dpo@volo-skin.fr</a></li>
                <li><strong>Courrier :</strong> VOLO SAS — Protection des donnees, 12 rue de la Paix, 75001 Paris</li>
            </ul>
            <p>
                En cas de difficulte en lien avec la gestion de vos donnees personnelles,
                vous pouvez introduire une reclamation aupres de la CNIL (www.cnil.fr).
            </p>

            <h2>8. Cookies</h2>
            <p>
                Le site utilise exclusivement des cookies strictement necessaires a son
                fonctionnement technique (authentification, protection CSRF). Aucun cookie de
                tracage, d'analyse ou publicitaire n'est utilise. Le detail est disponible dans
                notre <a href="/politique-confidentialite">politique de confidentialite</a>,
                section « Cookies ».
            </p>

            <h2>9. Credits</h2>
            <ul>
                <li><strong>Conception et developpement :</strong> [A completer]</li>
                <li><strong>Design et identite visuelle :</strong> [A completer]</li>
                <li><strong>Photographies produits :</strong> [Credit photographe ou banque d'images — a completer]</li>
                <li><strong>Icones :</strong> [Credit si applicable — a completer]</li>
            </ul>

            <h2>10. Droit applicable</h2>
            <p>
                Les presentes mentions legales sont regies par le droit francais. En cas de
                litige, et apres tentative de resolution amiable, les tribunaux francais seront
                seuls competents.
            </p>
        </div>
    </div>
);

export default MentionsLegalesPage;
