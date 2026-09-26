<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Response;

/**
 * robots.txt et sitemap.xml : les pages publiques, dans les trois langues,
 * avec leurs equivalents (hreflang). L'espace client n'y figure pas.
 */
class PlanDuSiteController extends Controller
{
    private const LANGUES = ['fr', 'nl', 'en'];

    public function robots(): Response
    {
        $texte = implode("\n", [
            'User-agent: *',
            'Disallow: /api/',
            'Disallow: /stripe/',
            'Disallow: /langue/',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ]);

        return response($texte, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        // Chemin sans langue => date de derniere modification.
        $chemins = [
            '' => null,
            'tarifs' => null,
            'devis' => null,
            'suivi' => null,
            'register' => null,
        ];

        foreach (Page::where('publiee', true)->where('au_pied', true)->get(['slug', 'updated_at']) as $page) {
            $chemins['p/'.$page->slug] = $page->updated_at;
        }

        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'];

        foreach ($chemins as $chemin => $modifie) {
            foreach (self::LANGUES as $langue) {
                $xml[] = '  <url>';
                $xml[] = '    <loc>'.e(self::adresse($langue, $chemin)).'</loc>';
                foreach (self::LANGUES as $autre) {
                    $xml[] = '    <xhtml:link rel="alternate" hreflang="'.$autre.'-BE" href="'.e(self::adresse($autre, $chemin)).'"/>';
                }
                if ($modifie) {
                    $xml[] = '    <lastmod>'.$modifie->toDateString().'</lastmod>';
                }
                $xml[] = '  </url>';
            }
        }

        $xml[] = '</urlset>';

        return response(implode("\n", $xml)."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private static function adresse(string $langue, string $chemin): string
    {
        return url($langue.($chemin === '' ? '' : '/'.$chemin));
    }
}
