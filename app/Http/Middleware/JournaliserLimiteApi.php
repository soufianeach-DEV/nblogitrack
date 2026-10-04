<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inscrit au journal de l'API les appels arretes par la limite de debit
 * (429). Ils n'y figuraient pas : la limite passe avant le controle de la
 * cle, qui tenait seul le journal.
 *
 * La limite sert a refuser un flot d'appels sans travail : chaque refus ne
 * doit pas ecrire une ligne. Le premier refus de chaque minute est inscrit
 * pour une meme cle et une meme adresse IP, et cinq au plus par minute pour
 * une adresse IP, meme si elle presente une cle differente a chaque appel.
 */
class JournaliserLimiteApi
{
    public const MOTIF = 'limite_depassee';

    public const PAR_ADRESSE = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $depart = microtime(true);
        $reponse = $next($request);

        if ($reponse->getStatusCode() === 429) {
            $this->journaliser($request, $depart);
        }

        return $reponse;
    }

    private function journaliser(Request $request, float $depart): void
    {
        $jeton = (string) $request->bearerToken();
        $ip = (string) $request->ip();
        $minute = intdiv(now()->getTimestamp(), 60);

        if (! Cache::add('api429:'.sha1(strtok($jeton, '.').'|'.$ip).':'.$minute, true, 120)) {
            return;
        }

        if (RateLimiter::tooManyAttempts('api429:'.$ip, self::PAR_ADRESSE)) {
            return;
        }

        RateLimiter::hit('api429:'.$ip, 60);

        ApiRequest::create([
            'api_key_id' => $jeton !== '' ? ApiKey::depuisJeton($jeton)?->id : null,
            'method' => $request->method(),
            'path' => mb_substr($request->path(), 0, 255),
            'status' => 429,
            'ip_address' => $ip,
            'duration_ms' => (int) round((microtime(true) - $depart) * 1000),
            'refus' => self::MOTIF,
            'created_at' => now(),
        ]);
    }
}
