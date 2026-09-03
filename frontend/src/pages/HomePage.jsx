/*
===============================================================================
Page : HomePage
===============================================================================
Objectif :
    Page d'accueil "Showcase" de VOLO, inspiree de la structure The Ordinary.

Responsabilites :
    - Definir le titre et la description de la page d'accueil (SEO).
    - Hero banner full-width avec image et slogan.
    - Bande horizontale "Problematiques" avec photos rondes (style The Ordinary).
    - Routines en defilement horizontal infini (marquee), classees par problematique.
    - Section "Bonnes habitudes de vie" (conseils lifestyle).
    - Sections empilees verticalement en pleine largeur.

Exemple d'utilisation :
    <Route path="/" element={<HomePage />} />
===============================================================================
*/

import { useNavigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import styles from './HomePage.module.css';

// Problematiques de peau (image + slug pour filtrer le catalogue)
const concerns = [
    { title: "Vieillissement", slug: "vieillissement", image: "/images/concerns/vieillissement.jpg" },
    { title: "Acné & Imperfections", slug: "acne", image: "/images/concerns/acne.jpg" },
    { title: "Hyperpigmentation", slug: "hyperpigmentation", image: "/images/concerns/hyperpigmentation.jpg" },
    { title: "Sécheresse", slug: "secheresse", image: "/images/concerns/secheresse.jpg" },
    { title: "Sensibilité", slug: "sensibilite", image: "/images/concerns/eclat.jpg" },
    { title: "Teint terne & Éclat", slug: "eclat", image: "/images/concerns/eclat.jpg" },
];

// Routines organisees PAR PROBLEMATIQUE (et non par niveau debutant/expert)
const routines = [
    {
        concern: "Acné & Imperfections",
        slug: "acne",
        name: "Routine Anti-Imperfections",
        steps: ["Nettoyant purifiant", "Sérum acide salicylique", "Crème non comédogène"],
    },
    {
        concern: "Vieillissement",
        slug: "vieillissement",
        name: "Routine Anti-Âge",
        steps: ["Nettoyant doux", "Sérum rétinol", "Crème raffermissante"],
    },
    {
        concern: "Sécheresse",
        slug: "secheresse",
        name: "Routine Hydratation Intense",
        steps: ["Huile démaquillante", "Sérum acide hyaluronique", "Baume nourrissant"],
    },
    {
        concern: "Hyperpigmentation",
        slug: "hyperpigmentation",
        name: "Routine Éclat & Unification",
        steps: ["Nettoyant éclaircissant", "Sérum vitamine C", "Protection solaire SPF50"],
    },
    {
        concern: "Sensibilité",
        slug: "sensibilite",
        name: "Routine Apaisante",
        steps: ["Nettoyant sans savon", "Sérum centella asiatica", "Crème barrière"],
    },
    {
        concern: "Teint terne & Éclat",
        slug: "eclat",
        name: "Routine Coup d'Éclat",
        steps: ["Exfoliant doux", "Sérum niacinamide", "Crème illuminatrice"],
    },
];

// Conseils de bonnes habitudes de vie pour la peau
const lifestyleTips = [
    { slug: "hydratation", icon: "💧", title: "Hydratation", desc: "Buvez au moins 1,5L d'eau par jour pour maintenir l'élasticité de la peau." },
    { slug: "sommeil", icon: "😴", title: "Sommeil réparateur", desc: "7 à 8h de sommeil favorisent la régénération cellulaire nocturne." },
    { slug: "protection-solaire", icon: "☀️", title: "Protection solaire", desc: "Appliquez un SPF chaque jour, même par temps couvert." },
    { slug: "alimentation", icon: "🥗", title: "Alimentation équilibrée", desc: "Privilégiez fruits, légumes et oméga-3 pour nourrir la peau de l'intérieur." },
];

// Reset des styles par defaut du navigateur pour un <button> utilise comme carte cliquable
const resetButtonStyle = {
    border: 'none',
    background: 'none',
    padding: 0,
    font: 'inherit',
    textAlign: 'inherit',
    cursor: 'pointer',
};

// Duplique pour boucle infinie fluide (technique marquee classique).
// Statique : ne depend d'aucune prop/etat, donc calcule une seule fois.
const loopedRoutines = [
    ...routines.map((r) => ({ ...r, uid: `${r.slug}-a` })),
    ...routines.map((r) => ({ ...r, uid: `${r.slug}-b` })),
];

export const HomePage = () => {
    const navigate = useNavigate();

    const handleConcernClick = (slug) => {
        navigate(`/soins?skin_concern=${slug}`);
    };

    const handleRoutineClick = (slug) => {
        navigate(`/soins?skin_concern=${slug}`);
    };

    return (
        <div>
            <Helmet>
                <title>VOLO — Skincare inclusive, peau saine et douce</title>
                <meta
                    name="description"
                    content="Decouvrez des routines et produits skincare adaptes a votre peau : acne, secheresse, hyperpigmentation, sensibilite. Livraison offerte."
                />
                <meta property="og:title" content="VOLO — Skincare inclusive" />
                <meta property="og:description" content="Routines et produits skincare adaptes a votre peau. Livraison offerte." />
                <meta property="og:type" content="website" />
                <meta property="og:image" content="/images/hero.jpg" />
            </Helmet>

            {/* 1. HERO SECTION - Banniere full-width */}
            <div className={styles.hero}>
                <img
                    src="/images/hero.jpg"
                    alt="VOLO"
                    className={styles.heroImage}
                />
                <div className={styles.heroOverlay}>
                    <h1 className={styles.heroTitle}>VOLO</h1>
                    <p className={styles.heroSlogan}>
                        La simplicité est l'ultime sophistication.
                    </p>
                </div>
            </div>

            {/* 2. PROBLEMATIQUES - Bande horizontale avec photos rondes */}
            <section className={styles.section}>
                <h2 className={styles.sectionTitle}>Problèmes Courants</h2>
                <p className={styles.sectionSubtitle}>
                    Vous ne savez pas où commencer ? Voici quelques problèmes de peau courants.
                </p>
                <div className={styles.concernsRow}>
                    {concerns.map((concern) => (
                        <button
                            type="button"
                            key={concern.slug}
                            className={styles.concernItem}
                            style={resetButtonStyle}
                            onClick={() => handleConcernClick(concern.slug)}
                        >
                            <div className={styles.concernImageWrapper}>
                                <img
                                    src={concern.image}
                                    alt={concern.title}
                                    className={styles.concernImage}
                                    loading="lazy"
                                />
                            </div>
                            <span className={styles.concernLabel}>{concern.title}</span>
                        </button>
                    ))}
                </div>
            </section>

            {/* 3. ROUTINES - Defilement horizontal infini par problematique */}
            <section className={`${styles.section} ${styles.sectionAlt} ${styles.noPadX}`}>
                <h2 className={styles.sectionTitle}>Trouvez votre routine</h2>
                <p className={styles.sectionSubtitle}>
                    Une routine adaptée à votre problématique, pas à votre niveau.
                </p>
                <div className={styles.marqueeWrapper}>
                    <div className={styles.marqueeTrack}>
                        {loopedRoutines.map((routine) => (
                            <button
                                type="button"
                                key={routine.uid}
                                className={styles.routineCard}
                                style={resetButtonStyle}
                                onClick={() => handleRoutineClick(routine.slug)}
                            >
                                <span className={styles.routineTag}>{routine.concern}</span>
                                <h3 className={styles.routineName}>{routine.name}</h3>
                                <ul className={styles.routineSteps}>
                                    {routine.steps.map((step) => (
                                        <li key={step}>{step}</li>
                                    ))}
                                </ul>
                            </button>
                        ))}
                    </div>
                </div>
            </section>

            {/* 4. BONNES HABITUDES DE VIE */}
            <section className={styles.section}>
                <h2 className={styles.sectionTitle}>Bonnes habitudes de vie</h2>
                <p className={styles.sectionSubtitle}>
                    Une belle peau commence aussi par de bonnes habitudes au quotidien.
                </p>
                <div className={styles.tipsGrid}>
                    {lifestyleTips.map((tip) => (
                        <div key={tip.slug} className={styles.tipCard}>
                            <div className={styles.tipIcon}>{tip.icon}</div>
                            <h3 className={styles.tipTitle}>{tip.title}</h3>
                            <p className={styles.tipDesc}>{tip.desc}</p>
                        </div>
                    ))}
                </div>
            </section>

        </div>
    );
};

export default HomePage;
