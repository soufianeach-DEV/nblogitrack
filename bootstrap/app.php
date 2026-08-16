<?php

use App\Http\Middleware\AuthentifierCleApi;
use App\Http\Middleware\DefinirLangue;
use App\Http\Middleware\EnTetesDeSecurite;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\VerifierCompteActif;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // A12 : l'API des partenaires, sans session ni cookie. Elle est
        // servie sous /api, hors du prefixe de langue : une machine
        // n'a pas de langue d'interface.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // DefinirLangue passe avant Inertia : le partage des traductions
        // lit la langue deja choisie.
        // VerifierCompteActif passe en premier : inutile de traduire une
        // page et de partager un dictionnaire pour quelqu'un qu'on va
        // renvoyer a l'ecran de connexion.
        $middleware->web(append: [
            EnTetesDeSecurite::class,
            VerifierCompteActif::class,
            DefinirLangue::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Stripe notifie le paiement depuis ses serveurs : aucune session,
        // donc aucun jeton de formulaire a presenter. L'appel est authentifie
        // par la signature de son en-tete, verifiee dans le controleur.
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);

        // A12 : le controle des cles, declare par son alias pour que la
        // permission exigee se lise sur la route (cle.api:ecriture).
        $middleware->alias([
            'cle.api' => AuthentifierCleApi::class,
        ]);

        /*
         * Derriere un proxy, l'adresse vue par l'application est celle du
         * proxy et non celle du visiteur. Six choses en dependent : la
         * liste blanche d'adresses des cles d'API, le journal d'acces a
         * l'API, le journal d'activite, l'accuse de prise de connaissance
         * du conducteur, la limite du suivi anonyme et celle des essais de
         * connexion. Toutes deviendraient fausses, et la liste blanche
         * carrement inoperante.
         *
         * L'hebergeur n'est pas choisi : la liste se declare a la mise en
         * production plutot que d'etre devinee ici. Tant qu'elle est vide,
         * on ne fait confiance a personne, ce qui est le bon defaut quand
         * l'application repond directement.
         *
         * Faire confiance a X-Forwarded-Proto retablit aussi
         * $request->secure(), dont depend l'en-tete HSTS.
         */
        $proxies = trim((string) env('TRUSTED_PROXIES', ''));

        if ($proxies !== '') {
            $middleware->trustProxies(
                at: $proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies)))),
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO,
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Une session dure deux heures. Passe ce delai, le jeton du
        // formulaire ne correspond plus et Laravel repond « 419 PAGE
        // EXPIRED » : une page nue qui ne dit rien et ou l'utilisateur reste
        // bloque, y compris quand il essayait simplement de se deconnecter.
        //
        // Sa session n'existe plus, il est donc deja deconnecte : on le
        // ramene a l'ecran de connexion en lui disant pourquoi.
        //
        // Le filtre porte sur le code et non sur TokenMismatchException :
        // Laravel convertit celle-ci en HttpException avant d'appeler les
        // gestionnaires, si bien qu'un filtre sur la classe d'origine ne se
        // declencherait jamais.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message' => 'Votre session a expiré, reconnectez-vous.',
                ], 419);
            }

            return redirect()
                ->route('login')
                ->with('status', 'Votre session a expiré. Reconnectez-vous pour continuer.');
        });
    })->create();
