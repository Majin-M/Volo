import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { HelmetProvider } from 'react-helmet-async';
import CGVPage from './CGVPage';
import MentionsLegalesPage from './MentionsLegalesPage';
import PolitiqueConfidentialitePage from './PolitiqueConfidentialitePage';

/*
 * Ces tests lisent le TEXTE RENDU, et non le code source : c'est volontaire.
 *
 * Les informations d'identite sont injectees depuis src/config/legalIdentity.js.
 * Or JSX supprime l'espace lorsqu'une expression commence une ligne apres du
 * texte : « edite par⏎{EDITEUR.raisonSociale} » s'affiche « edite parVOLO SAS ».
 * Une recherche dans le code ne voit pas ce defaut ; seul le rendu le montre.
 */
const rendre = (Page) => {
    const { container } = render(
        <HelmetProvider>
            <Page />
        </HelmetProvider>
    );
    return container.textContent.replace(/\s+/g, ' ');
};

// Valeurs reelles retirees, ou marqueurs jamais remplis.
const RESTES_INTERDITS = /volo-skin|rue de la Paix|\(a completer\)|\[[^\]]*completer\]|123 456 789/i;

describe('Pages legales — identite fictive de pre-production', () => {
    it.each([
        ['CGV', CGVPage],
        ['Mentions legales', MentionsLegalesPage],
        ['Politique de confidentialite', PolitiqueConfidentialitePage],
    ])('%s : affiche le bandeau et ne contient plus aucune valeur a completer', (_nom, Page) => {
        const texte = rendre(Page);

        expect(screen.getByRole('note')).toHaveTextContent('informations d\'identification de cette page');
        expect(texte).not.toMatch(RESTES_INTERDITS);
    });

    it('CGV : la phrase d\'identification est complete et correctement espacee', () => {
        const texte = rendre(CGVPage);

        expect(texte).toContain(
            "sur le site volo.example, edite par VOLO SAS, au capital de 10 000 euros, immatriculee au RCS de Exempleville sous le numero 000 000 000, dont le siege social est situe au 1 rue de l'Exemple, 00000 Exempleville, France."
        );
        expect(texte).toContain('Mediateur Exemple de la consommation');
        // Formule juridique du formulaire de retractation : ce n'est PAS un marqueur.
        expect(texte).toContain('a completer et renvoyer uniquement si');
    });

    it('Mentions legales : editeur et hebergeur sont renseignes', () => {
        const texte = rendre(MentionsLegalesPage);

        expect(texte).toContain('Le site volo.example est edite par');
        expect(texte).toContain('SIRET : 000 000 000 00000');
        expect(texte).toContain('Hebergeur Exemple SAS');
        expect(texte).toContain('Directeur de la publication : Camille Exemple');
    });

    it('Politique de confidentialite : responsable du traitement correctement espace', () => {
        const texte = rendre(PolitiqueConfidentialitePage);

        expect(texte).toContain('collectees sur le site volo.example est');
        expect(texte).toContain('dpo@volo.example');
    });
});
