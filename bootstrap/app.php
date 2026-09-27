<?php

use App\Http\Middleware\AuthentifierCleApi;
use App\Http\Middleware\DefinirLangue;
use App\Http\Middleware\EnTetesDeSecurite;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IgnorerFiltresEnTableau;
use App\Http\Middleware\MesurerAudience;
use App\Http\Middleware\RetirerCaracteresDeControle;
use App\Http\Middleware\VerifierCompteActif;
use App\Models\Translation;
use App\Support\Audience;
use App\Support\Traductions;
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
        // Les en-tetes de securite s'appliquent a toutes les reponses, API
        // et pages d'erreur comprises : places dans le groupe web, ils
        // manquaient sur une 404 levee avant lui (modele introuvable dans
        // l'adresse) et sur les reponses de l'API.
        $middleware->append(EnTetesDeSecurite::class);
        $middleware->append(RetirerCaracteresDeControle::class);

        $middleware->web(append: [
            IgnorerFiltresEnTableau::class,
            VerifierCompteActif::class,
            DefinirLangue::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            MesurerAudience::class,
        ]);

        // Le choix du bandeau est pose par le navigateur, en clair : le
        // serveur le lit tel quel.
        $middleware->encryptCookies(except: [Audience::TEMOIN]);

        // Un visiteur non connecte revient a l'ecran de connexion dans la
        // langue de l'adresse demandee (/nl/missions -> /nl/login).
        $middleware->redirectGuestsTo(function (Request $request) {
            $langue = $request->segment(1);

            return route('login', ['langue' => Traductions::estServie($langue) ? $langue : 'fr']);
        });

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
        // La liste se lit a chaque requete dans config/trustedproxy.php :
        // lue ici, avant le chargement du fichier .env, elle restait vide
        // en production.
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Seuls le domaine de APP_URL et ses sous-domaines sont servis : un
        // en-tete Host force ne se retrouve plus dans les liens absolus
        // (robots.txt, plan du site, balises canoniques). Sans effet en
        // developpement local et pendant les tests.
        $middleware->trustHosts();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // L'API repond toujours en JSON, meme a un appelant qui n'envoie
        // pas Accept: application/json : une erreur de validation ou une
        // limite de debit depassee repondait par une redirection HTML.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api', 'api/*') || $request->expectsJson(),
        );

        // Une adresse inconnue (/nl/inexistant, /en/p/nope) echoue avant
        // que la langue soit fixee : la page d'erreur sortait en francais.
        // On la lit dans le premier segment de l'adresse.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $langue = $request->segment(1);

            if (Traductions::estServie($langue)) {
                app()->setLocale($langue);
            }

            return null;
        });

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
                    'message' => Traductions::t('msg.session_expiree_courte', 'Votre session a expiré, reconnectez-vous.'),
                ], 419);
            }

            return redirect()
                ->route('login')
                ->with('status', Traductions::t('msg.session_expiree', 'Votre session a expiré. Reconnectez-vous pour continuer.'));
        });

        // API : la limite de debit repond en JSON, dans la langue demandee.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 429 || ! $request->is('api', 'api/*')) {
                return null;
            }

            $langue = $request->getPreferredLanguage(array_keys(Translation::LANGUES));

            if (trim((string) $request->header('Accept-Language')) !== '' && Traductions::estServie($langue)) {
                app()->setLocale($langue);
            }

            $secondes = (int) ($e->getHeaders()['Retry-After'] ?? 60);

            return response()->json([
                'message' => Traductions::t('api.trop_de_requetes', 'Trop de requêtes. Réessayez dans :secondes secondes.', ['secondes' => max(1, $secondes)]),
            ], 429, $e->getHeaders());
        });

        // Une limite de debit depassee repondait par une fenetre brute
        // « 429 TOO MANY REQUESTS », en anglais, par-dessus le formulaire.
        // L'utilisateur reste sur sa page, ses champs intacts, avec un
        // message qui dit combien de temps attendre.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 429 || $request->is('api', 'api/*') || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
                return null;
            }

            $secondes = (int) ($e->getHeaders()['Retry-After'] ?? 60);

            // Retour a la page precedente, sauf si c'est l'adresse bloquee
            // elle-meme (lien du courriel de suivi ouvert apres trop
            // d'essais) : le retour la redemandait, et la redirection
            // tournait en boucle. On repart alors de la meme page sans ses
            // parametres.
            $precedente = url()->previous();
            $memeAdresse = strtok($precedente, '?') === $request->url();
            $cible = $request->isMethod('GET') && ($memeAdresse || $precedente === url('/'))
                ? $request->url()
                : $precedente;

            if ($request->isMethod('GET') && $cible === $request->fullUrl()) {
                return null;
            }

            return redirect()->to($cible)->with('error', Traductions::t('msg.trop_de_demandes', 'Trop de tentatives en peu de temps. Réessayez dans :secondes secondes.', [
                'secondes' => max(1, $secondes),
            ]));
        });

        // Un refus pendant la navigation (lien vers un ecran d'un autre
        // role) s'ouvrait dans une fenetre d'erreur brute, en anglais. On
        // reste sur la page, avec un message dans la langue de l'ecran.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 403 || ! $request->header('X-Inertia')) {
                return null;
            }

            // Ouvert directement (lien, retour apres connexion), l'ecran
            // refuse est aussi la page precedente : y revenir bouclait.
            $precedente = url()->previous();
            $cible = $request->isMethod('GET') && strtok($precedente, '?') === $request->url() ? route('dashboard') : $precedente;

            if ($request->isMethod('GET') && $cible === $request->fullUrl()) {
                return null;
            }

            return redirect()->to($cible)->with('error', Traductions::t('msg.acces_refuse', 'Vous n\'avez pas accès à cet écran.'));
        });
    })->create();
