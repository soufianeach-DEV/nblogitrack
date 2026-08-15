<?php

namespace App\Http\Middleware;

use App\Models\Page;
use App\Models\Translation;
use App\Support\Traductions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'canPlan' => (bool) $request->user()?->isStaff(),
                'canViewLogs' => (bool) $request->user()?->isAdmin(),
                'canHandleQuotes' => (bool) $request->user()?->isStaff(),
                'canValidateClients' => (bool) $request->user()?->isAdmin(),
                'canManageUsers' => (bool) $request->user()?->isAdmin(),
                'canViewFleet' => (bool) $request->user()?->isStaff(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                // Sans ce partage, un back()->with('error') n'atteignait
                // jamais la page : le message etait ecrit et perdu.
                'error' => fn () => $request->session()->get('error'),
                // A12 : la valeur d'une cle fraichement generee. Elle ne
                // transite qu'une fois, dans cette session, et n'est
                // enregistree nulle part.
                'cle_en_clair' => fn () => $request->session()->get('cle_en_clair'),
            ],
            // Le dictionnaire complet part avec chaque page. Il tient en
            // cache cote serveur et vaut quelques kilo-octets : moins cher
            // qu'un aller-retour supplementaire a chaque navigation.
            //
            // Il s'appelle dictionnaire et non traductions : une prop de
            // page ecrase silencieusement une prop partagee de meme nom, et
            // l'ecran d'administration renvoie justement sa liste sous le
            // nom traductions. Le layout y perdait sa langue sans erreur.
            'langue' => app()->getLocale(),
            'langues' => Translation::LANGUES,
            'dictionnaire' => fn () => Traductions::pour(app()->getLocale()),
            // A13 : les pages legales du pied de vitrine. Partagees plutot
            // que passees page par page, parce que le pied s'affiche
            // partout ; en cache pour la meme raison que le dictionnaire.
            'pages_pied' => fn () => Cache::remember(
                'pages.pied.'.app()->getLocale(),
                Traductions::DUREE_CACHE,
                fn () => Page::where('publiee', true)->where('au_pied', true)
                    ->orderBy('rang')->orderBy('slug')->get()
                    ->map(fn (Page $p) => [
                        'libelle' => $p->titre(app()->getLocale()),
                        'href' => route('pages.show', $p->slug),
                    ])->all(),
            ),
        ];
    }
}
