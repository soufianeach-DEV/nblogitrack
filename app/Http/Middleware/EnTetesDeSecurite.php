<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Les en-tetes que le navigateur applique a notre place.
 *
 * Sans eux, la page de connexion et celle du paiement peuvent etre
 * chargees dans un cadre invisible pose sur un autre site, et un clic
 * destine a ce site atterrit chez nous. Un fichier televerse peut aussi
 * etre requalifie par le navigateur et execute comme du script.
 *
 * La politique de contenu est ecrite a partir de ce que l'application
 * appelle reellement, pas d'une liste recopiee : Photon, la BAN
 * francaise et le PDOK neerlandais pour completer une adresse, OSRM
 * pour estimer une distance, CARTO pour les tuiles de carte. Tout le
 * reste vient de nous, polices comprises.
 */
class EnTetesDeSecurite
{
    /** Ce que le navigateur a le droit d'aller chercher ailleurs. */
    private const IMAGES = 'https://*.basemaps.cartocdn.com';

    private const APPELS = 'https://photon.komoot.io https://router.project-osrm.org https://api-adresse.data.gouv.fr https://api.pdok.nl';

    public function handle(Request $request, Closure $next): Response
    {
        // Un seul nonce pour la page, tire avant le rendu et confie a
        // Laravel : il en marque ses propres balises, Ziggy le reprend
        // dans le gabarit, et la politique n'autorise que lui. Il change
        // a chaque page, si bien qu'un script injecte ne peut pas le
        // deviner.
        $nonce = Vite::useCspNonce();

        $reponse = $next($request);

        // PHP annonce sa version dans chaque reponse. C'est une aide
        // gratuite pour qui cherche une faille connue.
        header_remove('X-Powered-By');
        $reponse->headers->remove('X-Powered-By');

        foreach ($this->entetes($request, $nonce) as $nom => $valeur) {
            $reponse->headers->set($nom, $valeur);
        }

        return $reponse;
    }

    /** @return array<string, string> */
    private function entetes(Request $request, string $nonce): array
    {
        $entetes = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',

            // La geolocalisation reste ouverte a l'application elle-meme :
            // l'ecran du chauffeur en depend. Le reste est ferme, y compris
            // pour les cadres, qu'on n'utilise pas.
            'Permissions-Policy' => 'geolocation=(self), camera=(), microphone=(), payment=(), usb=()',
        ];

        // La politique de contenu ne s'applique qu'aux fichiers construits.
        //
        // En developpement, Vite sert ses modules depuis http://[::1]:5173.
        // Une adresse IPv6 entre crochets n'est pas une source valide au
        // sens de la specification : les navigateurs la rejettent, et la
        // politique bloque alors l'application entiere. Plutot que d'ecrire
        // une politique fausse pour contenter l'outillage, on l'applique la
        // ou elle protege vraiment : sur ce qui est deploye.
        if (! Vite::isRunningHot()) {
            $entetes['Content-Security-Policy'] = $this->politique($nonce);
        }

        // Annoncer HSTS sur une connexion en clair n'a aucun sens et
        // pourrait rendre le site injoignable en developpement.
        if ($request->secure()) {
            $entetes['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $entetes;
    }

    /**
     * Le seul script en ligne autorise est celui de Ziggy, qui porte le
     * nonce du jour. Tout autre script injecte dans la page est refuse
     * par le navigateur avant d'etre lu.
     */
    private function politique(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            // Leaflet et React posent des styles en ligne sur les elements
            // qu'ils fabriquent : les interdire casserait la carte.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: ".self::IMAGES,
            "font-src 'self'",
            "connect-src 'self' ".self::APPELS,
        ]);
    }
}
