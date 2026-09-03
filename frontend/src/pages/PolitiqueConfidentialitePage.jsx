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
    - Preciser les transferts internationaux de donnees.
    - Fournir les coordonnees du responsable de traitement et du DPO.
    - Informer sur la protection des donnees des mineurs.

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
                content="Politique de confidentialite VOLO : donnees collectees, droits RGPD, cookies, transferts internationaux et durees de conservation."
            />
        </Helmet>

        <h1 className={styles.pageTitle}>Politique de confidentialite</h1>
        <p className={styles.lastUpdated}>Derniere mise a jour : 2 septembre 2026</p>

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
                <li>SIRET : 123 456 789 00001 (a completer)</li>
            </ul>
            <p>
                <strong>Contact delegue a la protection des donnees (DPO) :</strong>{' '}
                <a href="mailto:dpo@volo-skin.fr">dpo@volo-skin.fr</a>
            </p>

            <h2>2. Donnees collectees</h2>
            <p>
                Nous collectons uniquement les donnees strictement necessaires aux finalites
                decrites ci-dessous. Aucune donnee n'est collectee a l'insu de l'utilisateur.
            </p>

            <h3>2.1 Lors de la creation de compte</h3>
            <ul>
                <li>Prenom, nom</li>
                <li>Adresse email</li>
                <li>Mot de passe (stocke sous forme de hachage irreversible via bcrypt — le mot de passe en clair n'est jamais stocke)</li>
            </ul>

            <h3>2.2 Lors d'une commande</h3>
            <ul>
                <li>Adresse de livraison (rue, ville, code postal, pays)</li>
                <li>Donnees de paiement (traitees directement par Stripe — jamais stockees sur nos serveurs, voir section 5)</li>
                <li>Historique des commandes (references, montants, statuts)</li>
            </ul>

            <h3>2.3 Lors de l'utilisation du formulaire de contact</h3>
            <ul>
                <li>Prenom</li>
                <li>Adresse email</li>
                <li>Sujet et contenu du message</li>
            </ul>

            <h3>2.4 Donnees collectees automatiquement</h3>
            <ul>
                <li>Journaux de connexion (adresse IP, date, heure — obligation legale)</li>
                <li>Donnees techniques de navigation (type de navigateur, systeme d'exploitation — a des fins de compatibilite technique uniquement)</li>
            </ul>

            <h2>3. Finalites et bases legales</h2>
            <p>Vos donnees sont traitees pour les finalites suivantes :</p>

            <table style={{ width: '100%', borderCollapse: 'collapse', margin: '10px 0 14px 0', fontSize: '0.92em' }}>
                <thead>
                    <tr style={{ borderBottom: '2px solid #E9D7C3' }}>
                        <th style={{ textAlign: 'left', padding: '8px 10px' }}>Finalite</th>
                        <th style={{ textAlign: 'left', padding: '8px 10px' }}>Base legale (RGPD)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style={{ borderBottom: '1px solid #E9D7C3' }}>
                        <td style={{ padding: '8px 10px' }}>Gestion des comptes utilisateurs</td>
                        <td style={{ padding: '8px 10px' }}>Execution du contrat (art. 6.1.b)</td>
                    </tr>
                    <tr style={{ borderBottom: '1px solid #E9D7C3' }}>
                        <td style={{ padding: '8px 10px' }}>Traitement et suivi des commandes</td>
                        <td style={{ padding: '8px 10px' }}>Execution du contrat (art. 6.1.b)</td>
                    </tr>
                    <tr style={{ borderBottom: '1px solid #E9D7C3' }}>
                        <td style={{ padding: '8px 10px' }}>Traitement des demandes de contact</td>
                        <td style={{ padding: '8px 10px' }}>Interet legitime (art. 6.1.f)</td>
                    </tr>
                    <tr style={{ borderBottom: '1px solid #E9D7C3' }}>
                        <td style={{ padding: '8px 10px' }}>Obligations legales (facturation, comptabilite)</td>
                        <td style={{ padding: '8px 10px' }}>Obligation legale (art. 6.1.c)</td>
                    </tr>
                    <tr style={{ borderBottom: '1px solid #E9D7C3' }}>
                        <td style={{ padding: '8px 10px' }}>Conservation des journaux de connexion</td>
                        <td style={{ padding: '8px 10px' }}>Obligation legale (art. 6.1.c)</td>
                    </tr>
                    <tr>
                        <td style={{ padding: '8px 10px' }}>Envoi d'email de confirmation de commande</td>
                        <td style={{ padding: '8px 10px' }}>Execution du contrat (art. 6.1.b)</td>
                    </tr>
                </tbody>
            </table>

            <h2>4. Destinataires des donnees</h2>
            <p>Vos donnees personnelles peuvent etre transmises aux destinataires suivants :</p>
            <ul>
                <li>
                    <strong>Stripe, Inc.</strong> (San Francisco, Etats-Unis) — pour le traitement
                    securise des paiements par carte bancaire. Stripe est certifie PCI DSS niveau 1.
                    Voir section 5 pour les transferts internationaux.
                </li>
                <li>
                    <strong>Notre hebergeur</strong> ([a completer]) — pour l'hebergement technique
                    du site et des donnees.
                </li>
            </ul>
            <p>
                Aucune donnee n'est vendue, louee ou cedee a des tiers a des fins commerciales,
                publicitaires ou de prospection.
            </p>

            <h2>5. Transferts internationaux de donnees</h2>
            <p>
                Le recours a Stripe pour le traitement des paiements implique un transfert de
                donnees vers les Etats-Unis. Ce transfert est encadre par :
            </p>
            <ul>
                <li>
                    Le <strong>EU-U.S. Data Privacy Framework</strong> (DPF), auquel Stripe est
                    certifie, conformement a la decision d'adequation de la Commission europeenne
                    du 10 juillet 2023.
                </li>
                <li>
                    Des <strong>clauses contractuelles types</strong> (CCT) adoptees par la Commission
                    europeenne, integrees au contrat de sous-traitance avec Stripe.
                </li>
            </ul>
            <p>
                Les donnees transmises a Stripe se limitent strictement aux informations
                necessaires au traitement du paiement. Les numeros de carte bancaire sont
                traites exclusivement par Stripe et ne transitent jamais par nos serveurs.
            </p>

            <h2>6. Durees de conservation</h2>
            <ul>
                <li>
                    <strong>Donnees de compte :</strong> conservees tant que le compte est actif,
                    puis 3 ans apres la derniere connexion (prescription civile).
                </li>
                <li>
                    <strong>Donnees de commande :</strong> 5 ans a compter de la commande
                    (obligations comptables legales, art. L123-22 du Code de commerce).
                </li>
                <li>
                    <strong>Donnees de paiement :</strong> conservees par Stripe conformement a
                    leurs propres obligations legales. VOLO SAS ne conserve que la reference de
                    la transaction.
                </li>
                <li>
                    <strong>Messages de contact :</strong> 1 an apres le traitement complet de la
                    demande.
                </li>
                <li>
                    <strong>Journaux de connexion :</strong> 12 mois (art. 6 II de la LCEN et
                    decret n° 2011-219 du 25 fevrier 2011).
                </li>
                <li>
                    <strong>Donnees supprimees (soft delete) :</strong> les comptes supprimes sont
                    desactives et anonymises. Les donnees sont conservees pour la duree legale
                    restante, puis definitivement purgees.
                </li>
            </ul>
            <p>
                A l'expiration des durees ci-dessus, les donnees sont supprimees ou anonymisees
                de maniere irreversible.
            </p>

            <h2>7. Cookies</h2>
            <p>Le site utilise exclusivement des cookies strictement necessaires a son fonctionnement :</p>

            <table style={{ width: '100%', borderCollapse: 'collapse', margin: '10px 0 14px 0', fontSize: '0.92em' }}>
                <thead>
                    <tr style={{ borderBottom: '2px solid #E9D7C3' }}>
                        <th style={{ textAlign: 'left', padding: '8px 10px' }}>Nom</th>
                        <th style={{ textAlign: 'left', padding: '8px 10px' }}>Finalite</th>
                        <th style={{ textAlign: 'left', padding: '8px 10px' }}>Duree</th>
                        <th style={{ textAlign: 'left', padding: '8px 10px' }}>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style={{ borderBottom: '1px solid #E9D7C3' }}>
                        <td style={{ padding: '8px 10px' }}><strong>volo_token</strong></td>
                        <td style={{ padding: '8px 10px' }}>Authentification (jeton JWT)</td>
                        <td style={{ padding: '8px 10px' }}>Session (fermeture du navigateur)</td>
                        <td style={{ padding: '8px 10px' }}>HttpOnly, Secure</td>
                    </tr>
                    <tr>
                        <td style={{ padding: '8px 10px' }}><strong>volo_csrf</strong></td>
                        <td style={{ padding: '8px 10px' }}>Protection CSRF (securite des formulaires)</td>
                        <td style={{ padding: '8px 10px' }}>Session (fermeture du navigateur)</td>
                        <td style={{ padding: '8px 10px' }}>Secure</td>
                    </tr>
                </tbody>
            </table>

            <p>
                Ces cookies sont indispensables au fonctionnement du site et ne necessitent pas
                de consentement prealable (art. 82 de la loi Informatique et Libertes, exemption
                pour les cookies strictement necessaires, transposant l'article 5(3) de la
                directive 2002/58/CE).
            </p>
            <p>
                <strong>Aucun cookie de tracage, d'analyse, de publicite ou de reseau social
                n'est utilise.</strong> Aucun cookie tiers n'est depose a l'exception de ceux
                strictement necessaires au fonctionnement de Stripe lors du processus de
                paiement.
            </p>

            <h2>8. Profilage et decisions automatisees</h2>
            <p>
                VOLO SAS ne realise <strong>aucun profilage</strong> au sens de l'article 22 du
                RGPD. Aucune decision fondee exclusivement sur un traitement automatise
                produisant des effets juridiques ou affectant de maniere significative
                l'utilisateur n'est prise.
            </p>
            <p>
                Les recommandations de produits eventuellement affichees sur le site sont
                basees sur des criteres generiques (categorie de peau, type de produit) et ne
                constituent pas du profilage individuel.
            </p>

            <h2>9. Vos droits</h2>
            <p>
                Conformement au RGPD (articles 15 a 22) et a la loi Informatique et Libertes,
                vous disposez des droits suivants sur vos donnees personnelles :
            </p>
            <ul>
                <li>
                    <strong>Droit d'acces (art. 15) :</strong> obtenir la confirmation que vos
                    donnees sont traitees et en recevoir une copie.
                </li>
                <li>
                    <strong>Droit de rectification (art. 16) :</strong> faire corriger des
                    donnees inexactes ou incompletes.
                </li>
                <li>
                    <strong>Droit a l'effacement (art. 17) :</strong> demander la suppression de
                    vos donnees, sous reserve des obligations legales de conservation.
                </li>
                <li>
                    <strong>Droit a la limitation du traitement (art. 18) :</strong> restreindre
                    le traitement de vos donnees dans certains cas (contestation de l'exactitude,
                    opposition en cours d'examen).
                </li>
                <li>
                    <strong>Droit a la portabilite (art. 20) :</strong> recevoir vos donnees
                    dans un format structure, couramment utilise et lisible par machine (JSON),
                    et les transmettre a un autre responsable de traitement.
                </li>
                <li>
                    <strong>Droit d'opposition (art. 21) :</strong> vous opposer au traitement de
                    vos donnees pour des raisons tenant a votre situation particuliere.
                </li>
                <li>
                    <strong>Droit de definir des directives post-mortem :</strong> conformement a
                    la loi Informatique et Libertes, definir des directives relatives a la
                    conservation, l'effacement et la communication de vos donnees apres votre
                    deces.
                </li>
            </ul>

            <h3>9.1 Comment exercer vos droits</h3>
            <p>
                Pour exercer l'un de ces droits, adressez votre demande :
            </p>
            <ul>
                <li>Par email a <a href="mailto:dpo@volo-skin.fr">dpo@volo-skin.fr</a></li>
                <li>Par courrier a VOLO SAS — Protection des donnees, 12 rue de la Paix, 75001 Paris</li>
            </ul>
            <p>
                Votre demande doit indiquer vos nom, prenom, adresse email associee au compte,
                et etre accompagnee d'un justificatif d'identite en cas de doute raisonnable
                sur votre identite.
            </p>
            <p>
                Nous nous engageons a repondre dans un delai d'un mois a compter de la
                reception de la demande. Ce delai peut etre prolonge de deux mois supplementaires
                en cas de complexite ou de nombre eleve de demandes, avec information prealable.
            </p>

            <h2>10. Protection des donnees des mineurs</h2>
            <p>
                Le site volo-skin.fr n'est pas destine aux enfants de moins de 16 ans. Nous ne
                collectons pas sciemment de donnees personnelles aupres de mineurs de moins de
                16 ans sans le consentement du titulaire de l'autorite parentale, conformement a
                l'article 8 du RGPD et a l'article 45 de la loi Informatique et Libertes.
            </p>
            <p>
                Si nous decouvrons qu'un mineur de moins de 16 ans a cree un compte sans
                consentement parental, les donnees seront supprimees dans les meilleurs delais.
            </p>

            <h2>11. Securite des donnees</h2>
            <p>
                Nous mettons en oeuvre les mesures techniques et organisationnelles appropriees
                pour proteger vos donnees contre tout acces non autorise, modification,
                divulgation ou destruction :
            </p>
            <ul>
                <li>Chiffrement des communications (HTTPS/TLS sur l'ensemble du site).</li>
                <li>Hachage irreversible des mots de passe (bcrypt avec salage automatique).</li>
                <li>Cookies d'authentification HttpOnly et Secure (inaccessibles au JavaScript).</li>
                <li>Protection CSRF sur toutes les actions sensibles (commandes, paiements, modifications de compte).</li>
                <li>En-tetes de securite HTTP (Content-Security-Policy, Strict-Transport-Security, Permissions-Policy, X-Content-Type-Options).</li>
                <li>Paiements traites par Stripe (certifie PCI DSS niveau 1).</li>
                <li>Journal d'audit des operations sensibles (creation, modification, suppression de donnees).</li>
                <li>Politique de moindre privilege sur les acces aux donnees.</li>
            </ul>

            <h2>12. Violation de donnees</h2>
            <p>
                En cas de violation de donnees a caractere personnel susceptible d'engendrer un
                risque pour les droits et libertes des personnes concernees, VOLO SAS notifiera
                la CNIL dans un delai de 72 heures conformement a l'article 33 du RGPD.
            </p>
            <p>
                Si la violation est susceptible d'engendrer un risque eleve, les personnes
                concernees seront egalement informees dans les meilleurs delais (art. 34 RGPD).
            </p>

            <h2>13. Modifications de la politique</h2>
            <p>
                VOLO SAS se reserve le droit de modifier la presente politique de
                confidentialite a tout moment. En cas de modification substantielle, les
                utilisateurs enregistres seront informes par email. La date de derniere mise a
                jour est indiquee en haut de cette page.
            </p>
            <p>
                La poursuite de l'utilisation du site apres modification vaut acceptation de la
                nouvelle politique.
            </p>

            <h2>14. Reclamation</h2>
            <p>
                Si vous estimez que le traitement de vos donnees constitue une violation du RGPD
                ou de la loi Informatique et Libertes, vous avez le droit d'introduire une
                reclamation aupres de l'autorite de controle competente :
            </p>
            <ul>
                <li><strong>CNIL</strong> — Commission Nationale de l'Informatique et des Libertes</li>
                <li>3 Place de Fontenoy, TSA 80715, 75334 Paris Cedex 07</li>
                <li>Telephone : 01 53 73 22 22</li>
                <li>Site : <a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer">www.cnil.fr</a></li>
            </ul>
            <p>
                Nous vous encourageons a nous contacter au prealable a l'adresse{' '}
                <a href="mailto:dpo@volo-skin.fr">dpo@volo-skin.fr</a> afin de resoudre toute
                difficulte de maniere amiable.
            </p>
        </div>
    </div>
);

export default PolitiqueConfidentialitePage;
