<?php

/*
===============================================================================
Contrôleur : SitemapController
===============================================================================
Objectif :
    Generer dynamiquement le sitemap.xml du site a partir des donnees reelles
    (produits disponibles, problematiques peau, pages statiques publiques).

Responsabilites :
    - Lister les URLs publiques indexables avec leurs metadonnees
      (frequence de mise a jour, priorite, date de derniere modification).
    - Exclure volontairement les pages privees (panier, connexion,
      inscription, commande) — deja exclues via robots.txt egalement.

Routes disponibles :
    - GET /sitemap.xml

Securite :
    Public

Dependances :
    - ProductRepository
    - SkinConcernRepository
===============================================================================
*/

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\SkinConcernRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SitemapController extends AbstractController
{
    /**
     * @param Request $request Requete HTTP courante, pour construire les URLs absolues.
     * @param ProductRepository $productRepository Pour lister les produits disponibles.
     * @param SkinConcernRepository $skinConcernRepository Pour lister les filtres par problematique.
     * @return Response Document XML du sitemap.
     */
    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        SkinConcernRepository $skinConcernRepository
    ): Response {
        $baseUrl = $request->getSchemeAndHttpHost();
        $urls = [];

        $urls[] = ['loc' => $baseUrl . '/', 'changefreq' => 'daily', 'priority' => '1.0'];
        $urls[] = ['loc' => $baseUrl . '/soins', 'changefreq' => 'daily', 'priority' => '0.9'];
        $urls[] = ['loc' => $baseUrl . '/contact', 'changefreq' => 'monthly', 'priority' => '0.5'];
        $urls[] = ['loc' => $baseUrl . '/mentions-legales', 'changefreq' => 'yearly', 'priority' => '0.3'];
        $urls[] = ['loc' => $baseUrl . '/politique-confidentialite', 'changefreq' => 'yearly', 'priority' => '0.3'];
        $urls[] = ['loc' => $baseUrl . '/cgv', 'changefreq' => 'yearly', 'priority' => '0.3'];

        foreach ($productRepository->findBy(['isAvailable' => true]) as $product) {
            $urls[] = [
                'loc' => $baseUrl . '/soins/' . $product->getId(),
                'lastmod' => $product->getUpdatedAt()?->format('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        foreach ($skinConcernRepository->findAll() as $concern) {
            $urls[] = [
                'loc' => $baseUrl . '/soins?skin_concern=' . $concern->getSlug(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }

        return new Response($this->buildXml($urls), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Construit le document XML au format Sitemap Protocol 0.9.
     *
     * @param array $urls Liste d'URLs avec leurs metadonnees (loc, lastmod, changefreq, priority).
     * @return string Document XML complet.
     */
    private function buildXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc']) . "</loc>\n";
            if (!empty($url['lastmod'])) {
                $xml .= '    <lastmod>' . $url['lastmod'] . "</lastmod>\n";
            }
            if (!empty($url['changefreq'])) {
                $xml .= '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
            }
            if (!empty($url['priority'])) {
                $xml .= '    <priority>' . $url['priority'] . "</priority>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
