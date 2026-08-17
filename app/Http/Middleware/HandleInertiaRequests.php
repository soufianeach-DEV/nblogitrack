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
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
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
                'error' => fn () => $request->session()->get('error'),
                'cle_en_clair' => fn () => $request->session()->get('cle_en_clair'),
            ],
            'langue' => app()->getLocale(),
            'langues' => Translation::LANGUES,
            'dictionnaire' => fn () => Traductions::pour(app()->getLocale()),
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
