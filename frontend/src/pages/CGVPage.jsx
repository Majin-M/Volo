/*
===============================================================================
Page : CGVPage
===============================================================================
Objectif :
    Afficher les Conditions Generales de Vente, obligatoires pour tout site
    de commerce en ligne en France (Code de la consommation, art. L111-1 et
    suivants).

Responsabilites :
    - Definir le cadre contractuel des ventes (objet, prix, commande).
    - Decrire les modalites de paiement, livraison, retractation.
    - Informer sur les garanties legales.

Exemple d'utilisation :
    <Route path="/cgv" element={<CGVPage />} />
===============================================================================
*/

import { Helmet } from 'react-helmet-async';
import styles from './LegalPage.module.css';

const CGVPage = () => (
    <div className={styles.container}>
        <Helmet>
            <title>Conditions generales de vente — VOLO</title>
            <meta
                name="description"
                content="Conditions generales de vente VOLO : commandes, paiement, livraison, retractation et garanties."
            />
        </Helmet>

        <h1 className={styles.pageTitle}>Conditions generales de vente</h1>
        <p className={styles.lastUpdated}>Derniere mise a jour : 31 aout 2026</p>

        <div className={styles.content}>
            <h2>1. Objet</h2>
            <p>
                Les presentes Conditions Generales de Vente (CGV) regissent les ventes de
                produits cosmetiques effectuees sur le site volo-skin.fr, edite par VOLO SAS.
            </p>
            <p>
                Toute commande passee sur le site implique l'acceptation sans reserve des
                presentes CGV.
            </p>

            <h2>2. Produits</h2>
            <p>
                Les produits proposes a la vente sont decrits sur chaque fiche produit avec
                la plus grande exactitude possible. Les photographies illustratives n'entrent
                pas dans le champ contractuel.
            </p>
            <p>
                VOLO SAS se reserve le droit de modifier l'assortiment de produits a tout
                moment. En cas d'indisponibilite d'un produit apres passation de la commande,
                le client sera informe et rembourse dans les meilleurs delais.
            </p>

            <h2>3. Prix</h2>
            <p>
                Les prix sont indiques en euros, toutes taxes comprises (TTC). Ils sont valables
                au moment de la validation de la commande.
            </p>
            <p>
                VOLO SAS se reserve le droit de modifier ses prix a tout moment, mais les
                produits seront factures au tarif en vigueur lors de la validation de la
                commande.
            </p>

            <h2>4. Commande</h2>
            <p>Le processus de commande comprend les etapes suivantes :</p>
            <ul>
                <li>Selection des produits et ajout au panier.</li>
                <li>Verification du contenu du panier.</li>
                <li>Saisie de l'adresse de livraison.</li>
                <li>Choix du mode de paiement et validation.</li>
                <li>Confirmation de la commande.</li>
            </ul>
            <p>
                La validation de la commande vaut acceptation des presentes CGV et constitue
                la preuve du contrat de vente.
            </p>

            <h2>5. Paiement</h2>
            <p>Le paiement s'effectue par carte bancaire via la plateforme securisee Stripe.</p>
            <p>
                Les donnees de paiement sont traitees directement par Stripe (certifie PCI DSS
                niveau 1) et ne transitent jamais par nos serveurs. VOLO SAS n'a a aucun moment
                acces aux numeros de carte bancaire.
            </p>
            <p>
                La commande est validee a reception de la confirmation de paiement par Stripe.
            </p>

            <h2>6. Livraison</h2>
            <p>
                Les produits sont livres a l'adresse indiquee par le client lors de la
                commande. Les delais de livraison sont donnes a titre indicatif.
            </p>
            <p>
                En cas de retard de livraison superieur a 30 jours par rapport a la date
                prevue, le client peut annuler sa commande et obtenir un remboursement
                integral (art. L216-2 du Code de la consommation).
            </p>

            <h2>7. Droit de retractation</h2>
            <p>
                Conformement aux articles L221-18 et suivants du Code de la consommation, le
                client dispose d'un delai de <strong>14 jours</strong> a compter de la
                reception des produits pour exercer son droit de retractation, sans avoir a
                justifier de motif ni a payer de penalites.
            </p>
            <p>
                Pour exercer ce droit, le client doit notifier sa decision par email a{' '}
                <a href="mailto:contact@volo-skin.fr">contact@volo-skin.fr</a> avant
                l'expiration du delai.
            </p>
            <p>
                Les produits doivent etre retournes dans leur emballage d'origine, non ouverts
                et non utilises. Les frais de retour sont a la charge du client.
            </p>
            <p>
                Le remboursement sera effectue dans un delai de 14 jours a compter de la
                reception du retour, via le meme moyen de paiement.
            </p>
            <p>
                <strong>Exceptions :</strong> conformement a l'article L221-28 du Code de la
                consommation, le droit de retractation ne peut etre exerce pour les produits
                cosmetiques descelles apres livraison et ne pouvant etre renvoyes pour des
                raisons d'hygiene ou de protection de la sante.
            </p>

            <h2>8. Garanties legales</h2>
            <p>
                Independamment de toute garantie commerciale, le client beneficie des garanties
                legales prevues par le Code de la consommation :
            </p>
            <ul>
                <li>
                    <strong>Garantie de conformite</strong> (art. L217-4 et suivants) : le
                    produit doit etre conforme a sa description et propre a l'usage attendu.
                </li>
                <li>
                    <strong>Garantie des vices caches</strong> (art. 1641 et suivants du Code
                    civil) : le produit ne doit pas comporter de defauts caches le rendant
                    impropre a son usage.
                </li>
            </ul>

            <h2>9. Reclamations</h2>
            <p>
                Pour toute reclamation, le client peut contacter le service client a l'adresse{' '}
                <a href="mailto:contact@volo-skin.fr">contact@volo-skin.fr</a>.
            </p>
            <p>
                En cas de litige non resolu, le client peut recourir a un mediateur de la
                consommation conformement aux articles L611-1 et suivants du Code de la
                consommation. Le mediateur competent est : [Nom et coordonnees du mediateur a
                completer].
            </p>

            <h2>10. Protection des donnees</h2>
            <p>
                Les donnees personnelles collectees lors des commandes sont traitees
                conformement a notre{' '}
                <a href="/politique-confidentialite">politique de confidentialite</a>.
            </p>

            <h2>11. Droit applicable</h2>
            <p>
                Les presentes CGV sont soumises au droit francais. Tout litige relatif a leur
                interpretation ou a leur execution releve de la competence des tribunaux
                francais.
            </p>
        </div>
    </div>
);

export default CGVPage;
