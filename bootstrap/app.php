<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
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
