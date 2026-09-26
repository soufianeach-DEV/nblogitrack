<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Translation;
use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TranslationController extends Controller
{
    public function index(Request $request): Response
    {
        $recherche = trim((string) $request->query('recherche', ''));
        $groupe = $request->query('groupe');

        $traductions = Translation::query()
            ->when($groupe, fn ($q) => $q->where('groupe', $groupe))
            // « % » et « _ » se cherchent tels quels, pas comme jokers.
            ->when($recherche !== '', fn ($q) => $q->where(function ($w) use ($recherche) {
                $motif = '%'.addcslashes($recherche, '\\%_').'%';

                $w->where('cle', 'ilike', $motif)
                    ->orWhere('fr', 'ilike', $motif)
                    ->orWhere('nl', 'ilike', $motif)
                    ->orWhere('en', 'ilike', $motif);
            }))
            ->orderBy('groupe')->orderBy('cle')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Traductions/Index', [
            'traductions' => $traductions,
            'groupes' => Translation::selectRaw('groupe, count(*) AS total')
                ->groupBy('groupe')->orderBy('groupe')->get(),
            'langues' => Translation::LANGUES,
            'recherche' => $recherche,
            'groupe' => $groupe,
            'manquantes' => [
                'nl' => Translation::whereNull('nl')->orWhere('nl', '')->count(),
                'en' => Translation::whereNull('en')->orWhere('en', '')->count(),
                'total' => Translation::count(),
            ],
        ]);
    }

    public function update(Request $request, Translation $translation): RedirectResponse
    {
        $data = $request->validate([
            'fr' => 'required|string|max:2000',
            'nl' => 'nullable|string|max:2000',
            'en' => 'nullable|string|max:2000',
        ]);

        $avant = $translation->only(['fr', 'nl', 'en']);

        $translation->update([...$data, 'modifiee_a_la_main' => true]);
        Traductions::oublier();

        ActivityLog::record(
            'translation.updated',
            'Traduction '.$translation->cle.' modifiée',
            $translation,
            ['avant' => $avant, 'apres' => $data],
        );

        return back()->with('success', Traductions::t('msg.traduction_enregistree', 'Traduction « :cle » enregistrée.', ['cle' => $translation->cle]));
    }
}
