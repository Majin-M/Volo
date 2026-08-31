/*
===============================================================================
Composant : Footer
===============================================================================
Objectif :
    Afficher le pied de page du site.

Responsabilites :
    - Presenter la marque et sa mission (VOLO).
    - Liens legaux : Contact, Mentions legales, Reseaux sociaux.
    - Design sobre et propre pour ne pas surcharger la navigation.

Exemple d'utilisation :
    <Footer />
===============================================================================
*/

// Styles statiques (ne dependent d'aucune prop/etat) : definis en portee
// module pour ne pas etre reconstruits a chaque rendu.
const footerStyle = {
    backgroundColor: '#5F4C42',
    color: '#F8F0E8',
    padding: '60px 20px',
    marginTop: '80px',
    fontFamily: 'Lato, sans-serif',
};

const containerStyle = {
    maxWidth: '1200px',
    margin: '0 auto',
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
    gap: '40px',
};

const linkStyle = {
    color: '#E9D7C3',
    textDecoration: 'none',
    fontSize: '0.9em',
    cursor: 'pointer',
};

const headingStyle = {
    color: '#fff',
    fontFamily: 'Playfair Display, serif',
    marginBottom: '15px',
    fontSize: '1.2em',
};

const Footer = () => {
    return (
        <footer style={footerStyle}>
            <div style={containerStyle}>
                {/* Colonne 1 : Branding */}
                <div>
                    <h2 style={headingStyle}>VOLO</h2>
                    <p style={{ fontSize: '0.95em', lineHeight: '1.5' }}>
                        Laboratoire de la peau saine, douce et inclusive.
                        Retrouvez l'éclat de votre teint naturel.
                    </p>
                </div>

                {/* Colonne 2 : Navigation */}
                <div>
                    <h3 style={headingStyle}>Navigation</h3>
                    <ul style={{ listStyle: 'none', padding: 0, lineHeight: 2 }}>
                        <li><a href="/" style={linkStyle}>Accueil</a></li>
                        <li><a href="/soins" style={linkStyle}>Catalogue</a></li>
                        <li><a href="/propos" style={linkStyle}>À propos</a></li>
                    </ul>
                </div>

                {/* Colonne 3 : Contact */}
                <div>
                    <h3 style={headingStyle}>Contact</h3>
                    <p style={{ fontSize: '0.95em', marginBottom: '10px' }}>Besoin d'un conseil ?</p>
                    <a href="mailto:contact@volo-skin.fr" style={{
                        color: '#fff',
                        textDecoration: 'underline',
                        fontWeight: 'bold'
                    }}>
                        contact@volo-skin.fr
                    </a>
                </div>

                {/* Colonne 4 : Informations legales */}
                <div>
                    <h3 style={headingStyle}>Informations legales</h3>
                    <ul style={{ listStyle: 'none', padding: 0, lineHeight: 2 }}>
                        <li><a href="/mentions-legales" style={linkStyle}>Mentions legales</a></li>
                        <li><a href="/politique-confidentialite" style={linkStyle}>Politique de confidentialite</a></li>
                        <li><a href="/cgv" style={linkStyle}>Conditions generales de vente</a></li>
                    </ul>
                </div>
            </div>

            {/* Copyright Bas */}
            <div style={{ textAlign: 'center', borderTop: '1px solid #7a665e', marginTop: '40px', paddingTop: '20px', fontSize: '0.8em' }}>
                © 2024 VOLO. Tous droits réservés.
            </div>
        </footer>
    );
};

export default Footer;
