<?php

namespace App\Http\Middleware;

use App\Models\Translation;
use App\Support\Traductions;
use Illuminate\Http\Request;
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
        ];
    }
}
