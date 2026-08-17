<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class EnTetesDeSecurite
{
    private const IMAGES = 'https://*.basemaps.cartocdn.com';

    private const APPELS = 'https://photon.komoot.io https://router.project-osrm.org https://api-adresse.data.gouv.fr https://api.pdok.nl';

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $reponse = $next($request);

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

            'Permissions-Policy' => 'geolocation=(self), camera=(), microphone=(), payment=(), usb=()',
        ];

        if (! Vite::isRunningHot()) {
            $entetes['Content-Security-Policy'] = $this->politique($nonce);
        }

        if ($request->secure()) {
            $entetes['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $entetes;
    }

    private function politique(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: ".self::IMAGES,
            "font-src 'self'",
            "connect-src 'self' ".self::APPELS,
        ]);
    }
}
