<?php

namespace App\Support;

use App\Models\PageView;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Mesure d'audience du site vitrine, seulement avec l'accord du visiteur
 * (bandeau des temoins, categorie « Mesure d'audience »).
 *
 * Rien n'identifie le visiteur : pas de temoin de suivi, pas d'adresse IP,
 * pas d'empreinte du navigateur. On garde la page, la langue, la
 * provenance d'une arrivee (moteur, reseau social, campagne) et le type
 * d'appareil. Les comptes connectes et les robots ne sont pas comptes.
 */
final class Audience
{
    /** Temoin qui garde le choix du bandeau : « essentiels » ou « audience ». */
    public const TEMOIN = 'temoins_choix';

    /** Pages publiques mesurees. */
    public const PAGES = ['accueil', 'tarifs.index', 'devis.create', 'devis.confirmation', 'tracking.show', 'pages.show', 'login', 'register'];

    /** Conservation, en mois. */
    public const CONSERVATION = 13;

    /** Provenances connues, par nom de domaine. */
    private const SOURCES = [
        'google' => 'Google', 'bing' => 'Bing', 'duckduckgo' => 'DuckDuckGo', 'ecosia' => 'Ecosia', 'qwant' => 'Qwant', 'yahoo' => 'Yahoo',
        'linkedin' => 'LinkedIn', 'lnkd.in' => 'LinkedIn', 'facebook' => 'Facebook', 'fb.me' => 'Facebook', 'instagram' => 'Instagram',
        't.co' => 'X', 'twitter' => 'X', 'x.com' => 'X', 'whatsapp' => 'WhatsApp', 'youtube' => 'YouTube',
    ];

    public static function consentie(Request $request): bool
    {
        return $request->cookie(self::TEMOIN) === 'audience';
    }

    /** Page vue, si elle est mesurable. */
    public static function noterVue(Request $request): void
    {
        if (! $request->isMethod('GET') || ! self::mesurable($request)
            || ! in_array($request->route()?->getName(), self::PAGES, true)) {
            return;
        }

        self::noter($request, null);
    }

    /** Conversion : devis envoye, inscription, simulation de tarif. */
    public static function noterEvenement(Request $request, string $evenement): void
    {
        if (self::mesurable($request)) {
            self::noter($request, $evenement);
        }
    }

    private static function mesurable(Request $request): bool
    {
        return self::consentie($request)
            && $request->user() === null
            && ! preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse/i', (string) $request->userAgent());
    }

    private static function noter(Request $request, ?string $evenement): void
    {
        // Navigation interne (Inertia) ou lien depuis une autre page du site :
        // pas une arrivee.
        $referent = (string) $request->headers->get('referer', '');
        $hoteReferent = $referent === '' ? null : Str::lower((string) parse_url($referent, PHP_URL_HOST));
        $interne = $request->header('X-Inertia') !== null || $hoteReferent === Str::lower($request->getHost());
        $entree = $evenement === null && ! $interne;

        PageView::create([
            'jour' => now()->toDateString(),
            'chemin' => self::chemin($request),
            'langue' => substr(app()->getLocale(), 0, 2),
            'entree' => $entree,
            'source' => $entree ? self::source($request->query('utm_source'), $hoteReferent) : null,
            'support' => $entree ? Str::limit((string) $request->query('utm_medium'), 50, '') ?: null : null,
            'campagne' => $entree ? Str::limit((string) $request->query('utm_campaign'), 100, '') ?: null : null,
            'appareil' => preg_match('/Mobile|Android|iPhone|iPad/i', (string) $request->userAgent()) ? 'mobile' : 'ordinateur',
            'evenement' => $evenement,
        ]);
    }

    /** « /fr/tarifs » -> « /tarifs », sans parametres. */
    private static function chemin(Request $request): string
    {
        $segments = explode('/', trim($request->path(), '/'));

        if (in_array($segments[0] ?? '', ['fr', 'nl', 'en'], true)) {
            array_shift($segments);
        }

        return Str::limit('/'.implode('/', $segments), 255, '');
    }

    /** Campagne (utm_source), sinon domaine de provenance, sinon « Direct ». */
    public static function source(mixed $utm, ?string $hote): string
    {
        // Un meme reseau porte le meme nom, qu'il vienne d'un lien
        // (lnkd.in) ou d'une campagne (utm_source=linkedin).
        foreach (self::SOURCES as $motif => $nom) {
            if ((is_string($utm) && Str::lower(trim($utm)) === $motif) || ($hote !== null && str_contains($hote, $motif))) {
                return $nom;
            }
        }

        if (is_string($utm) && trim($utm) !== '') {
            return Str::limit(Str::ucfirst(trim($utm)), 100, '');
        }

        if ($hote === null || $hote === '') {
            return 'Direct';
        }

        return Str::limit(preg_replace('/^(www|m)\./', '', $hote), 100, '');
    }
}
