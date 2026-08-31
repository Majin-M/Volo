/*
===============================================================================
Page : PolitiqueConfidentialitePage
===============================================================================
Objectif :
    Informer les utilisateurs sur le traitement de leurs donnees personnelles
    conformement au RGPD (Reglement General sur la Protection des Donnees,
    UE 2016/679) et a la loi Informatique et Libertes.

Responsabilites :
    - Detailler les donnees collectees, leurs finalites et bases legales.
    - Expliquer les droits des utilisateurs (acces, rectification, suppression).
    - Decrire les cookies utilises par le site.
    - Indiquer les durees de conservation.
    - Fournir les coordonnees du responsable de traitement.

Exemple d'utilisation :
    <Route path="/politique-confidentialite" element={<PolitiqueConfidentialitePage />} />
===============================================================================
*/

import { Helmet } from 'react-helmet-async';
import styles from './LegalPage.module.css';

const PolitiqueConfidentialitePage = () => (
    <div className={styles.container}>
        <Helmet>
            <title>Politique de confidentialite — VOLO</title>
            <meta
                name="description"
                content="Politique de confidentialite VOLO : donnees collectees, droits RGPD, cookies et durees de conservation."
            />
        </Helmet>

        <h1 className={styles.pageTitle}>Politique de confidentialite</h1>
        <p className={styles.lastUpdated}>Derniere mise a jour : 31 aout 2026</p>

        <div className={styles.content}>
            <h2>1. Responsable du traitement</h2>
            <p>
                Le responsable du traitement des donnees personnelles collectees sur le site
                volo-skin.fr est :
            </p>
            <ul>
                <li><strong>VOLO SAS</strong></li>
                <li>12 rue de la Paix, 75001 Paris, France</li>
                <li>Email : <a href="mailto:contact@volo-skin.fr">contact@volo-skin.fr</a></li>
            </ul>

            <h2>2. Donnees collectees</h2>
            <p>Nous collectons les donnees suivantes :</p>

            <h3>2.1 Lors de la creation de compte</h3>
            <ul>
                <li>Prenom, nom</li>
                <li>Adresse email</li>
                <li>Mot de passe (stocke sous forme de hachage irreversible)</li>
            </ul>

            <h3>2.2 Lors d'une commande</h3>
            <ul>
                <li>Adresse de livraison (rue, ville, code postal, pays)</li>
                <li>Donnees de paiement (traitees directement par Stripe, jamais stockees sur nos serveurs)</li>
            </ul>

            <h3>2.3 Lors de l'utilisation du formulaire de contact</h3>
            <ul>
                <li>Prenom</li>
                <li>Adresse email</li>
                <li>Sujet et contenu du message</li>
            </ul>

            <h2>3. Finalites et bases legales</h2>
            <p>Vos donnees sont traitees pour les finalites suivantes :</p>
            <ul>
                <li>
                    <strong>Gestion des comptes utilisateurs</strong> — base legale : execution du
                    contrat (art. 6.1.b RGPD).
                </li>
                <li>
                    <strong>Traitement et suivi des commandes</strong> — base legale : execution du
                    contrat (art. 6.1.b RGPD).
                </li>
                <li>
                    <strong>Traitement des demandes de contact</strong> — base legale : interet
                    legitime (art. 6.1.f RGPD).
                </li>
                <li>
                    <strong>Obligations legales</strong> (facturation, comptabilite) — base legale :
                    obligation legale (art. 6.1.c RGPD).
                </li>
            </ul>

            <h2>4. Destinataires des donnees</h2>
            <p>Vos donnees personnelles peuvent etre transmises a :</p>
            <ul>
                <li><strong>Stripe</strong> — pour le traitement securise des paiements par carte bancaire.</li>
                <li><strong>Notre hebergeur</strong> — pour l'hebergement technique du site.</li>
            </ul>
            <p>
                Aucune donnee n'est vendue, louee ou cedee a des tiers a des fins commerciales
                ou publicitaires.
            </p>

            <h2>5. Durees de conservation</h2>
            <ul>
                <li><strong>Donnees de compte :</strong> conservees tant que le compte est actif, puis 3 ans apres la derniere connexion.</li>
                <li><strong>Donnees de commande :</strong> 5 ans a compter de la commande (obligations comptables legales).</li>
                <li><strong>Messages de contact :</strong> 1 an apres le traitement de la demande.</li>
                <li><strong>Journaux de connexion :</strong> 12 mois (obligation legale).</li>
            </ul>

            <h2>6. Cookies</h2>
            <p>Le site utilise exclusivement des cookies strictement necessaires a son fonctionnement :</p>
            <ul>
                <li>
                    <strong>volo_token</strong> — cookie d'authentification (HttpOnly, Secure).
                    Contient le jeton de session. Expire a la fermeture du navigateur.
                </li>
                <li>
                    <strong>volo_csrf</strong> — cookie de protection CSRF. Garantit que les
                    actions sensibles (commandes, paiements) proviennent bien de notre site.
                    Expire a la fermeture du navigateur.
                </li>
            </ul>
            <p>
                Ces cookies sont indispensables au fonctionnement du site et ne necessitent pas
                de consentement prealable (art. 82 de la loi Informatique et Libertes, exemption
                pour les cookies strictement necessaires).
            </p>
            <p>
                <strong>Aucun cookie de tracage, d'analyse ou publicitaire n'est utilise.</strong>
            </p>

            <h2>7. Vos droits</h2>
            <p>
                Conformement au RGPD et a la loi Informatique et Libertes, vous disposez des droits suivants :
            </p>
            <ul>
                <li><strong>Droit d'acces :</strong> obtenir une copie de vos donnees personnelles.</li>
                <li><strong>Droit de rectification :</strong> corriger des donnees inexactes ou incompletes.</li>
                <li><strong>Droit a l'effacement :</strong> demander la suppression de vos donnees.</li>
                <li><strong>Droit a la limitation :</strong> restreindre le traitement de vos donnees.</li>
                <li><strong>Droit a la portabilite :</strong> recevoir vos donnees dans un format structure.</li>
                <li><strong>Droit d'opposition :</strong> vous opposer au traitement de vos donnees.</li>
            </ul>
            <p>
                Pour exercer ces droits, envoyez un email a{' '}
                <a href="mailto:contact@volo-skin.fr">contact@volo-skin.fr</a> en precisant
                votre demande et en joignant un justificatif d'identite.
            </p>
            <p>Nous nous engageons a repondre dans un delai d'un mois.</p>

            <h2>8. Securite</h2>
            <p>
                Nous mettons en oeuvre les mesures techniques et organisationnelles appropriees
                pour proteger vos donnees :
            </p>
            <ul>
                <li>Chiffrement des communications (HTTPS/TLS).</li>
                <li>Hachage irreversible des mots de passe (bcrypt).</li>
                <li>Cookies d'authentification HttpOnly et Secure.</li>
                <li>Protection CSRF sur toutes les actions sensibles.</li>
                <li>Paiements traites par Stripe (certifie PCI DSS niveau 1).</li>
            </ul>

            <h2>9. Reclamation</h2>
            <p>
                Si vous estimez que le traitement de vos donnees constitue une violation du RGPD,
                vous avez le droit d'introduire une reclamation aupres de la CNIL :
            </p>
            <ul>
                <li><strong>CNIL</strong> — Commission Nationale de l'Informatique et des Libertes</li>
                <li>3 Place de Fontenoy, TSA 80715, 75334 Paris Cedex 07</li>
                <li>Site : www.cnil.fr</li>
            </ul>
        </div>
    </div>
);

export default PolitiqueConfidentialitePage;
