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
    - Inclure le formulaire type de retractation (annexe obligatoire).
    - Couvrir les aspects specifiques aux produits cosmetiques.

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
        <p className={styles.lastUpdated}>Derniere mise a jour : 2 septembre 2026</p>

        <div className={styles.content}>
            <h2>1. Objet et champ d'application</h2>
            <p>
                Les presentes Conditions Generales de Vente (CGV) regissent l'ensemble des
                ventes de produits cosmetiques effectuees sur le site volo-skin.fr, edite par
                VOLO SAS, au capital de 10 000 euros, immatriculee au RCS de Paris sous le
                numero 123 456 789 (a completer), dont le siege social est situe au 12 rue de
                la Paix, 75001 Paris, France.
            </p>
            <p>
                Toute commande passee sur le site implique l'acceptation pleine et entiere des
                presentes CGV. Le client reconnait en avoir pris connaissance avant la validation
                de sa commande. VOLO SAS se reserve le droit de modifier les presentes CGV a tout
                moment ; les conditions applicables sont celles en vigueur a la date de la
                commande.
            </p>
            <p>
                Les presentes CGV sont applicables aux consommateurs et non-professionnels au
                sens de l'article liminaire du Code de la consommation.
            </p>

            <h2>2. Produits</h2>
            <p>
                Les produits proposes a la vente sont des produits cosmetiques au sens du
                reglement (CE) n° 1223/2009 du Parlement europeen et du Conseil relatif aux
                produits cosmetiques. Ils sont conformes a la legislation et a la reglementation
                francaise et europeenne en vigueur.
            </p>
            <p>
                Chaque fiche produit presente les caracteristiques essentielles du produit,
                notamment sa composition (liste INCI), son mode d'emploi, les precautions
                d'utilisation et les contre-indications eventuelles, conformement aux articles
                L111-1 et suivants du Code de la consommation.
            </p>
            <p>
                Les photographies illustrant les produits n'entrent pas dans le champ
                contractuel. En cas de difference entre la photographie et la description du
                produit, seule la description fait foi.
            </p>
            <p>
                VOLO SAS se reserve le droit de modifier l'assortiment de produits a tout
                moment. En cas d'indisponibilite d'un produit apres passation de la commande,
                le client sera informe dans les meilleurs delais et pourra choisir entre un
                produit de substitution de qualite et prix equivalents ou le remboursement
                integral du produit indisponible.
            </p>

            <h2>3. Prix</h2>
            <p>
                Les prix sont indiques en euros, toutes taxes comprises (TTC), hors frais de
                livraison. Les frais de livraison sont indiques avant la validation definitive
                de la commande.
            </p>
            <p>
                VOLO SAS se reserve le droit de modifier ses prix a tout moment. Les produits
                seront factures au tarif en vigueur lors de la validation de la commande par le
                client.
            </p>
            <p>
                En cas d'erreur manifeste de prix (prix derisoire, prix aberrant), VOLO SAS se
                reserve le droit d'annuler la commande, meme apres confirmation, et d'en
                informer le client.
            </p>

            <h2>4. Commande</h2>
            <p>Le processus de commande comprend les etapes suivantes :</p>
            <ul>
                <li>Creation d'un compte client ou connexion a un compte existant.</li>
                <li>Selection des produits et ajout au panier.</li>
                <li>Verification du contenu du panier et des quantites.</li>
                <li>Saisie ou confirmation de l'adresse de livraison.</li>
                <li>Acceptation des presentes CGV (case a cocher obligatoire).</li>
                <li>Choix du mode de paiement et validation du paiement.</li>
                <li>Confirmation de la commande par email.</li>
            </ul>
            <p>
                Le client dispose de la possibilite de verifier le detail de sa commande, son
                prix total et de corriger d'eventuelles erreurs avant de confirmer son
                acceptation, conformement a l'article 1127-2 du Code civil.
            </p>
            <p>
                La validation de la commande par le clic de confirmation constitue une
                signature electronique qui vaut preuve du consentement du client et de
                l'exigibilite des sommes dues.
            </p>
            <p>
                VOLO SAS se reserve le droit de refuser toute commande pour un motif legitime,
                notamment en cas de commande anormale, de mauvaise foi du client ou de litige
                existant.
            </p>
            <p>
                <strong>Age minimum :</strong> le client declare etre age d'au moins 16 ans. Les
                mineurs de moins de 16 ans doivent obtenir l'autorisation de leur representant
                legal pour passer commande.
            </p>

            <h2>5. Paiement</h2>
            <p>
                Le paiement s'effectue par carte bancaire (Visa, Mastercard, American Express)
                via la plateforme de paiement securisee Stripe.
            </p>
            <p>
                Les donnees de paiement sont traitees directement par Stripe, certifie PCI DSS
                niveau 1 (plus haut niveau de certification de securite des paiements). Les
                numeros de carte bancaire ne transitent jamais par les serveurs de VOLO SAS et
                ne sont jamais stockes par nos soins.
            </p>
            <p>
                La commande est validee a reception de la confirmation de paiement par Stripe.
                Le compte bancaire du client est debite au moment de la confirmation du
                paiement.
            </p>
            <p>
                En cas de refus de paiement par la banque emettrice, la commande est
                automatiquement annulee et le client en est informe.
            </p>

            <h2>6. Transfert de propriete et des risques</h2>
            <p>
                Le transfert de propriete des produits au profit du client n'est realise
                qu'apres paiement complet du prix par ce dernier, conformement a la clause
                de reserve de propriete prevue par l'article 2367 du Code civil.
            </p>
            <p>
                Le transfert des risques de perte et de deterioration des produits est
                effectue au moment de la livraison, c'est-a-dire lors de la prise de possession
                physique des produits par le client ou un tiers designe par lui.
            </p>

            <h2>7. Livraison</h2>
            <p>
                Les produits sont livres a l'adresse indiquee par le client lors de la
                commande. Les livraisons sont effectuees en France metropolitaine.
            </p>
            <p>
                Les delais de livraison sont communiques a titre indicatif lors de la validation
                de la commande. VOLO SAS s'engage a livrer dans un delai maximum de 30 jours a
                compter de la validation du paiement, conformement a l'article L216-1 du Code
                de la consommation.
            </p>
            <p>
                En cas de retard de livraison, le client peut, apres mise en demeure restee
                infructueuse dans un delai raisonnable, resoudre le contrat par lettre
                recommandee avec accuse de reception ou par ecrit sur un support durable, et
                obtenir le remboursement integral dans un delai de 14 jours (art. L216-2 et
                L216-3 du Code de la consommation).
            </p>
            <p>
                Le client est tenu de verifier l'etat de l'emballage et des produits a la
                reception. En cas de dommage constate, le client doit emettre des reserves
                aupres du transporteur et en informer VOLO SAS dans un delai de 3 jours
                ouvrables suivant la livraison.
            </p>

            <h2>8. Droit de retractation</h2>
            <p>
                Conformement aux articles L221-18 et suivants du Code de la consommation, le
                client dispose d'un delai de <strong>14 jours calendaires</strong> a compter de
                la reception des produits pour exercer son droit de retractation, sans avoir a
                justifier de motif ni a payer de penalites, a l'exception des frais de retour.
            </p>

            <h3>8.1 Exercice du droit de retractation</h3>
            <p>
                Pour exercer ce droit, le client doit notifier sa decision de retractation au
                moyen d'une declaration denouee de toute ambiguite, par exemple en utilisant le
                formulaire de retractation figurant en annexe des presentes CGV, en l'adressant
                par email a{' '}
                <a href="mailto:contact@volo-skin.fr">contact@volo-skin.fr</a> ou par courrier
                a VOLO SAS, 12 rue de la Paix, 75001 Paris, avant l'expiration du delai de 14
                jours.
            </p>

            <h3>8.2 Retour des produits</h3>
            <p>
                Les produits doivent etre retournes dans leur emballage d'origine, complets,
                non ouverts et non utilises, dans un delai de 14 jours suivant la notification
                de la retractation. Les frais de retour sont a la charge du client.
            </p>

            <h3>8.3 Remboursement</h3>
            <p>
                Le remboursement sera effectue dans un delai maximum de 14 jours a compter de
                la reception des produits retournes ou de la preuve d'expedition par le client,
                via le meme moyen de paiement que celui utilise pour la commande initiale, sauf
                accord expres du client pour un autre moyen.
            </p>

            <h3>8.4 Exceptions au droit de retractation</h3>
            <p>
                Conformement a l'article L221-28 du Code de la consommation, le droit de
                retractation ne peut etre exerce pour :
            </p>
            <ul>
                <li>
                    Les produits cosmetiques descelles par le client apres la livraison et qui
                    ne peuvent etre renvoyes pour des raisons d'hygiene ou de protection de la
                    sante.
                </li>
                <li>
                    Les produits qui, par leur nature, sont susceptibles de se deteriorer ou de
                    se perimer rapidement.
                </li>
            </ul>

            <h3>8.5 Formulaire type de retractation</h3>
            <p>
                Conformement a l'annexe de l'article L221-5 du Code de la consommation, voici
                le modele de formulaire de retractation (a completer et renvoyer uniquement si
                vous souhaitez exercer votre droit de retractation) :
            </p>
            <div style={{ fontStyle: 'italic', background: '#FAF5EF', padding: '16px', borderRadius: '6px', border: '1px solid #E9D7C3' }}>
                <p style={{ margin: '0 0 8px 0' }}>
                    A l'attention de VOLO SAS, 12 rue de la Paix, 75001 Paris —{' '}
                    <a href="mailto:contact@volo-skin.fr">contact@volo-skin.fr</a>
                </p>
                <p style={{ margin: '0 0 8px 0' }}>
                    Je vous notifie par la presente ma retractation du contrat portant sur la vente
                    du/des produit(s) ci-dessous :
                </p>
                <p style={{ margin: '0 0 4px 0' }}>— Reference de la commande : ____________________</p>
                <p style={{ margin: '0 0 4px 0' }}>— Produit(s) concerne(s) : ____________________</p>
                <p style={{ margin: '0 0 8px 0' }}>— Date de reception : ____________________</p>
                <p style={{ margin: '0 0 4px 0' }}>Nom du client : ____________________</p>
                <p style={{ margin: '0 0 8px 0' }}>Adresse du client : ____________________</p>
                <p style={{ margin: '0 0 4px 0' }}>Date : ____________________</p>
                <p style={{ margin: 0 }}>Signature (uniquement en cas de notification sur papier) : ____________________</p>
            </div>

            <h2>9. Garanties legales</h2>
            <p>
                Independamment de toute garantie commerciale, le client beneficie des garanties
                legales prevues par le Code de la consommation et le Code civil :
            </p>
            <ul>
                <li>
                    <strong>Garantie legale de conformite</strong> (art. L217-3 a L217-20 du
                    Code de la consommation) : le vendeur livre un bien conforme au contrat et
                    repond des defauts de conformite existant lors de la delivrance. Le client
                    dispose d'un delai de 2 ans a compter de la delivrance du bien pour agir.
                    Il peut choisir entre la reparation ou le remplacement du bien, sous
                    reserve des conditions de cout prevues par l'article L217-12.
                </li>
                <li>
                    <strong>Garantie legale des vices caches</strong> (art. 1641 a 1649 du Code
                    civil) : le vendeur est tenu de la garantie a raison des defauts caches de
                    la chose vendue qui la rendent impropre a l'usage auquel on la destine, ou
                    qui diminuent tellement cet usage que l'acheteur ne l'aurait pas acquise. Le
                    client doit agir dans un delai de 2 ans a compter de la decouverte du vice.
                </li>
            </ul>
            <p>
                Pour faire valoir ses garanties, le client doit informer VOLO SAS du defaut de
                conformite ou du vice cache par email a{' '}
                <a href="mailto:contact@volo-skin.fr">contact@volo-skin.fr</a>.
            </p>

            <h2>10. Responsabilite</h2>
            <p>
                Les produits proposes sont conformes a la legislation francaise et europeenne
                en vigueur relative aux produits cosmetiques (reglement CE 1223/2009). Il
                appartient au client de respecter les precautions d'emploi mentionnees sur
                chaque fiche produit et sur l'emballage.
            </p>
            <p>
                VOLO SAS ne saurait etre tenue responsable en cas de mauvaise utilisation du
                produit, de non-respect des precautions d'emploi, ou de reaction allergique
                individuelle. Il est recommande au client d'effectuer un test cutane avant la
                premiere utilisation de tout nouveau produit cosmetique.
            </p>
            <p>
                La responsabilite de VOLO SAS ne saurait etre engagee pour l'ensemble des
                inconvenients ou dommages inherents a l'utilisation du reseau Internet,
                notamment une rupture de service, une intrusion exterieure ou la presence de
                virus informatiques.
            </p>

            <h2>11. Force majeure</h2>
            <p>
                VOLO SAS ne pourra etre tenue responsable de l'inexecution totale ou partielle
                de ses obligations au titre du contrat si cette inexecution est imputable au
                client, au fait imprevisible et insurmontable d'un tiers au contrat, ou a un
                cas de force majeure tel que defini par l'article 1218 du Code civil,
                notamment : catastrophes naturelles, incendies, pandemies, greves, guerres,
                defaillance des reseaux de telecommunications ou des fournisseurs d'energie.
            </p>

            <h2>12. Protection des donnees personnelles</h2>
            <p>
                Les donnees personnelles collectees lors des commandes sont traitees
                conformement au Reglement General sur la Protection des Donnees (RGPD) et a la
                loi Informatique et Libertes. Le detail des traitements, des droits du client
                et des mesures de securite est expose dans notre{' '}
                <a href="/politique-confidentialite">politique de confidentialite</a>.
            </p>

            <h2>13. Service client et reclamations</h2>
            <p>
                Pour toute question, information ou reclamation, le service client est joignable :
            </p>
            <ul>
                <li>Par email : <a href="mailto:contact@volo-skin.fr">contact@volo-skin.fr</a></li>
                <li>Par courrier : VOLO SAS, 12 rue de la Paix, 75001 Paris, France</li>
                <li>Via le formulaire de contact du site</li>
            </ul>
            <p>
                VOLO SAS s'engage a traiter toute reclamation dans un delai de 30 jours
                ouvrables a compter de sa reception.
            </p>

            <h2>14. Mediation des litiges</h2>
            <p>
                Conformement aux articles L611-1 et suivants et R612-1 et suivants du Code de
                la consommation, en cas de litige non resolu par le service client, le client
                peut recourir gratuitement a un mediateur de la consommation.
            </p>
            <p>
                Le mediateur competent est :
            </p>
            <ul>
                <li><strong>Nom :</strong> [Nom du mediateur de la consommation — a completer]</li>
                <li><strong>Adresse :</strong> [Adresse du mediateur — a completer]</li>
                <li><strong>Site :</strong> [URL du mediateur — a completer]</li>
            </ul>
            <p>
                Le client peut egalement deposer sa reclamation sur la plateforme europeenne de
                reglement en ligne des litiges :{' '}
                <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener noreferrer">
                    https://ec.europa.eu/consumers/odr
                </a>.
            </p>

            <h2>15. Propriete intellectuelle</h2>
            <p>
                L'ensemble des elements du site volo-skin.fr (textes, images, logos, marques,
                graphismes, logiciels, base de donnees) est protege par le droit de la propriete
                intellectuelle. Toute reproduction, representation ou diffusion, totale ou
                partielle, sans autorisation ecrite prealable de VOLO SAS, est interdite et
                constitue une contrefacon sanctionnee par les articles L335-2 et suivants du
                Code de la propriete intellectuelle.
            </p>

            <h2>16. Droit applicable et juridiction competente</h2>
            <p>
                Les presentes CGV sont soumises au droit francais. Tout litige relatif a leur
                interpretation ou a leur execution releve de la competence exclusive des
                tribunaux francais.
            </p>
            <p>
                Conformement a l'article R631-3 du Code de la consommation, le consommateur
                peut saisir, a son choix, outre l'un des tribunaux territorialement competents
                en vertu du Code de procedure civile, la juridiction du lieu ou il demeurait au
                moment de la conclusion du contrat ou de la survenance du fait dommageable.
            </p>

            <h2>17. Integralite du contrat</h2>
            <p>
                Les presentes CGV et le recapitulatif de commande transmis au client constituent
                l'integralite du contrat entre les parties. Si l'une des clauses des presentes
                CGV venait a etre declaree nulle par une juridiction competente, les autres
                clauses conserveraient leur plein effet.
            </p>
        </div>
    </div>
);

export default CGVPage;
