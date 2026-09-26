<?php

namespace App\Http\Controllers;

use App\Models\PageView;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audience du site vitrine : pages vues, arrivees et leur provenance,
 * conversions (devis, inscriptions, simulations). Seules les visites des
 * personnes qui l'ont accepte sont comptees.
 */
class AudienceController extends Controller
{
    private const PERIODES = [7, 30, 90, 365];

    public function index(Request $request): Response
    {
        $jours = in_array((int) $request->query('jours'), self::PERIODES, true) ? (int) $request->query('jours') : 30;
        $debut = today()->subDays($jours - 1);
        $base = fn () => PageView::where('jour', '>=', $debut->toDateString());

        $vues = $base()->whereNull('evenement');
        $entrees = (clone $vues)->where('entree', true)->count();
        $evenements = $base()->whereNotNull('evenement')->selectRaw('evenement, count(*) as n')->groupBy('evenement')->pluck('n', 'evenement');

        // Une valeur par jour, jours sans visite compris.
        $parJour = $base()->whereNull('evenement')->selectRaw('jour, count(*) as vues, sum(case when entree then 1 else 0 end) as entrees')
            ->groupBy('jour')->get()->keyBy(fn ($l) => Carbon::parse($l->jour)->toDateString());
        $serie = collect(range(0, $jours - 1))->map(function (int $i) use ($debut, $parJour) {
            $jour = $debut->copy()->addDays($i)->toDateString();

            return ['jour' => $jour, 'vues' => (int) ($parJour[$jour]->vues ?? 0), 'entrees' => (int) ($parJour[$jour]->entrees ?? 0)];
        })->all();

        $classement = fn (string $colonne, bool $seulesEntrees = false) => $base()->whereNull('evenement')
            ->when($seulesEntrees, fn ($q) => $q->where('entree', true))
            ->whereNotNull($colonne)
            ->selectRaw("$colonne as libelle, count(*) as n")
            ->groupBy($colonne)->orderByDesc('n')->limit(10)->get()
            ->map(fn ($l) => ['libelle' => $l->libelle, 'nombre' => (int) $l->n])->all();

        $devis = (int) ($evenements['devis'] ?? 0);

        return Inertia::render('Audience/Index', [
            'jours' => $jours,
            'periodes' => self::PERIODES,
            'totaux' => [
                'vues' => (clone $vues)->count(),
                'entrees' => $entrees,
                'devis' => $devis,
                'inscriptions' => (int) ($evenements['inscription'] ?? 0),
                'simulations' => (int) ($evenements['simulation'] ?? 0),
                // Part des arrivees qui finissent en demande de devis.
                'conversion' => $entrees > 0 ? round(100 * $devis / $entrees, 1) : null,
                'mobile' => $vues->count() > 0 ? round(100 * $base()->whereNull('evenement')->where('appareil', 'mobile')->count() / $base()->whereNull('evenement')->count()) : null,
            ],
            'serie' => $serie,
            'pages' => $classement('chemin'),
            'sources' => $classement('source', true),
            'campagnes' => $classement('campagne', true),
        ]);
    }
}
