<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Indisponibilite;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\ControleAffectation;
use App\Support\Suggestions;
use App\Support\Traductions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller
{
    public function index(Request $request): Response
    {
        $filtres = $request->validate([
            // Une suggestion choisie (nom complet ou adresse electronique)
            // doit passer : jusqu'a 255 caracteres, la taille de l'e-mail.
            'q' => 'nullable|string|max:255',
            'permis' => 'nullable|string|max:8',
            'etat' => 'nullable|in:disponibles,indisponibles,adr,visite,permis,inaptes,sortis,conformite',
        ]);

        $requete = Driver::with('user:id,first_name,last_name,email,phone,is_active');

        // L'ecran est ouvert a tout le personnel, la fiche ne se modifie
        // que par l'administrateur : les donnees de gestion du personnel
        // (Driver::DONNEES_RH) ne partent que vers lui.
        $peutModifier = $request->user()->can('manage-fleet');

        if (! empty($filtres['q'])) {
            $terme = (string) $filtres['q'];
            // Le numero de permis n'est cherche que pour qui le voit :
            // sinon la recherche le laissait deviner chiffre par chiffre.
            $requete->where(fn ($q) => $q
                ->whereHas('user', fn ($u) => $u
                    ->whereNomContient($terme)
                    ->orWhereContient('email', $terme))
                ->when($peutModifier, fn ($q) => $q->orWhereContient('license_number', $terme)));
        }

        if (! empty($filtres['permis'])) {
            $requete->where('license_type', $filtres['permis']);
        }

        $visiteLimite = now()->subYear()->toDateString();
        $permisLimite = now()->addDays(60)->toDateString();
        $aujourdhui = now()->toDateString();

        // Un depart date dans le futur n'a pas encore eu lieu : le
        // chauffeur roule jusque-la.
        $parti = fn ($q) => $q->whereNotNull('left_on')->where('left_on', '<=', $aujourdhui);

        // Memes criteres que Driver::empechements (voir Driver::scopeInapte).
        $inapte = fn ($q) => $q->inapte();

        match ($filtres['etat'] ?? null) {
            'disponibles' => $requete->where('is_available', true)->whereNot($inapte),
            'indisponibles' => $requete->where('is_available', false),
            'adr' => $requete->where('adr_certified', true),
            'visite' => $requete->where('medical_exam_date', '<', $visiteLimite),
            'permis' => $requete->where('license_expiry', '<=', $permisLimite),
            'inaptes' => $requete->whereNot($parti)->where($inapte),
            'sortis' => $requete->where($parti),
            // Le lien « Voir les N » du tableau de bord arrive ici : meme
            // critere que son widget, donc meme nombre.
            'conformite' => $requete->where(DashboardController::chauffeursAMettreEnRegle()),
            default => null,
        };

        $missions = TransportOrder::whereNotNull('driver_id')
            ->selectRaw('driver_id, count(*) AS nombre')
            ->groupBy('driver_id')
            ->pluck('nombre', 'driver_id');

        $enCours = TransportOrder::whereIn('status', TransportOrder::ACTIFS)
            ->whereNotNull('driver_id')
            ->pluck('driver_id')
            ->unique()
            ->flip();

        return Inertia::render('Parc/Chauffeurs', [
            'suggestions' => self::noms(fn () => User::whereIn('id', Driver::query()->select('user_id')), $filtres['q'] ?? null),
            'chauffeurs' => $requete->with(['indisponibilites' => fn ($q) => $q->where('au', '>=', today()->toDateString())->orderBy('du')])
                ->get()->map(fn (Driver $d) => [
                    'id' => $d->id,
                    'nom' => trim(($d->user?->first_name ?? '').' '.($d->user?->last_name ?? '')) ?: Traductions::t('msg.compte_supprime', 'Compte supprimé'),
                    'email' => $d->user?->email,
                    'telephone' => $d->user?->phone,
                    'actif' => (bool) ($d->user?->is_active ?? false),
                    'permis' => $d->license_type,
                    'permis_echeance' => $d->license_expiry?->format('d/m/Y'),
                    'permis_bientot' => $d->license_expiry !== null
                        && $d->license_expiry->lte(now()->addDays(60)),
                    'adr' => (bool) $d->adr_certified,
                    'visite' => $d->medical_exam_date?->format('Y-m-d'),
                    'visite_affichee' => $d->medical_exam_date?->format('d/m/Y'),
                    'visite_perimee' => $d->medical_exam_date !== null
                        && $d->medical_exam_date->lt(now()->subYear()),
                    'code95' => $d->cpc_expiry?->format('Y-m-d'),
                    'code95_affiche' => $d->cpc_expiry?->format('d/m/Y'),
                    'tacho' => $d->tacho_card_expiry?->format('Y-m-d'),
                    'adr_fin' => $d->adr_expiry?->format('Y-m-d'),
                    // La periode sert a planifier ; son motif (« maladie »)
                    // et le commentaire libre qui le precise restent a qui
                    // gere la fiche.
                    'indisponibilites' => $d->indisponibilites->map(fn (Indisponibilite $i) => [
                        'id' => $i->id,
                        'resume' => $peutModifier ? $i->resume() : $i->periode(),
                        'commentaire' => $peutModifier ? $i->commentaire : null,
                    ])->all(),
                    'tacho_affiche' => $d->tacho_card_expiry?->format('d/m/Y'),
                    'statut' => self::statuts()[$d->employment_status] ?? $d->employment_status,
                    'statut_code' => $d->employment_status,
                    // La date de sortie reste : elle borne la planification.
                    'sorti_le' => $d->left_on?->format('d/m/Y'),
                    'depart_futur' => $d->left_on !== null && $d->left_on->gt(today()),
                    'empechements' => $d->empechements(),
                    'heures' => (float) $d->daily_driving_hours,
                    'disponible' => (bool) $d->is_available,
                    'missions' => (int) ($missions[$d->id] ?? 0),
                    'engage' => $enCours->has($d->id),
                ] + ($peutModifier ? [
                    'numero_permis' => $d->license_number,
                    'embauche' => $d->hired_on?->format('d/m/Y'),
                    'naissance' => $d->birth_date?->format('Y-m-d'),
                    'naissance_affichee' => $d->birth_date?->format('d/m/Y'),
                    'age' => $d->birth_date?->age,
                    'retraite_prevue' => $d->retirement_planned_on?->format('Y-m-d'),
                    'retraite_affichee' => $d->retirement_planned_on?->format('d/m/Y'),
                    'motif_sortie' => $d->departure_reason !== null
                        ? (self::motifsSortie()[$d->departure_reason] ?? $d->departure_reason)
                        : null,
                    'motif_sortie_code' => $d->departure_reason,
                ] : []))->sortBy('nom')->values()->all(),
            'permis' => Driver::distinct()->orderBy('license_type')->pluck('license_type'),
            'statuts' => self::statuts(),
            'motifsSortie' => self::motifsSortie(),
            'compteurs' => [
                'total' => Driver::count(),
                'disponibles' => Driver::where('is_available', true)->whereNot($inapte)->count(),
                'adr' => Driver::where('adr_certified', true)->count(),
                'visite' => Driver::where('medical_exam_date', '<', $visiteLimite)->count(),
                'inaptes' => Driver::whereNot($parti)->where($inapte)->count(),
                'sortis' => Driver::where($parti)->count(),
                'conformite' => Driver::where(DashboardController::chauffeursAMettreEnRegle())->count(),
            ],
            'filtres' => $filtres,
            'peutModifier' => $peutModifier,
        ]);
    }

    public function update(Request $request, Driver $driver): RedirectResponse
    {
        $donnees = $request->validate([
            'is_available' => 'required|boolean',
            'adr_certified' => 'required|boolean',
            'adr_expiry' => 'nullable|date',
            'medical_exam_date' => 'nullable|date|before_or_equal:today',
            // La colonne est obligatoire : un champ vide faisait une erreur
            // 500. Absent, il garde l'echeance enregistree.
            'license_expiry' => 'sometimes|required|date',
            'cpc_expiry' => 'nullable|date',
            'tacho_card_expiry' => 'nullable|date',
            'employment_status' => 'required|in:'.implode(',', array_keys(Driver::STATUTS)),
            // Des dates qui se contredisent (depart avant l'entree, entree
            // avant la naissance, chauffeur de six ans) etaient enregistrees.
            'hired_on' => 'nullable|date|before_or_equal:today|after_or_equal:1950-01-01',
            'birth_date' => 'nullable|date|after_or_equal:1930-01-01|before_or_equal:'.today()->subYears(18)->toDateString(),
            'retirement_planned_on' => 'nullable|date|after_or_equal:hired_on',
            'left_on' => 'nullable|date|after_or_equal:hired_on',
            'departure_reason' => 'nullable|in:'.implode(',', array_keys(Driver::MOTIFS_SORTIE)),
        ], [
            'license_expiry.required' => Traductions::t('msg.echeance_permis_requise', 'Indiquez l\'échéance du permis.'),
            'medical_exam_date.before_or_equal' => Traductions::t('msg.visite_future', 'La visite médicale ne peut pas être postérieure à aujourd\'hui.'),
            'hired_on.before_or_equal' => Traductions::t('msg.entree_future', 'La date d\'entrée en service ne peut pas être dans le futur.'),
            'birth_date.before_or_equal' => Traductions::t('msg.naissance_majeur', 'Un chauffeur a au moins 18 ans : vérifiez la date de naissance.'),
            'birth_date.after_or_equal' => Traductions::t('msg.naissance_ancienne', 'Cette date de naissance est trop ancienne : vérifiez l\'année.'),
            'hired_on.after_or_equal' => Traductions::t('msg.entree_ancienne', 'Cette date d\'entrée est trop ancienne : vérifiez l\'année.'),
            'retirement_planned_on.after_or_equal' => Traductions::t('msg.retraite_avant_entree', 'La retraite prévue ne peut pas précéder l\'entrée en service.'),
            'left_on.after_or_equal' => Traductions::t('msg.depart_avant_entree', 'Le départ ne peut pas précéder l\'entrée en service.'),
        ]);

        // Une case decochee peut arriver en « 0 » plutot qu'en false : les
        // gardes ci-dessous comparent a un vrai booleen.
        $donnees['is_available'] = $request->boolean('is_available');
        $donnees['adr_certified'] = $request->boolean('adr_certified');

        // L'entree en service suit les 18 ans du chauffeur.
        if (! empty($donnees['hired_on']) && ! empty($donnees['birth_date'])
            && \Illuminate\Support\Carbon::parse($donnees['hired_on'])->lt(\Illuminate\Support\Carbon::parse($donnees['birth_date'])->addYears(18))) {
            return back()->withErrors([
                'hired_on' => Traductions::t('msg.entree_avant_majorite', 'L\'entrée en service ne peut pas précéder les 18 ans du chauffeur.'),
            ]);
        }

        if (! empty($donnees['left_on']) && empty($donnees['departure_reason'])) {
            return back()->withErrors([
                'departure_reason' => Traductions::t('msg.motif_depart_requis', 'Indiquez le motif du départ.'),
            ]);
        }

        if (! empty($donnees['left_on'])) {
            $encore = TransportOrder::whereIn('status', TransportOrder::ACTIFS)
                ->where('driver_id', $driver->id)
                ->exists();

            if ($encore) {
                return back()->withErrors([
                    'left_on' => Traductions::t('msg.chauffeur_engage_depart', 'Ce chauffeur porte une mission en cours : réaffectez-la avant d\'enregistrer son départ.'),
                ]);
            }

            // Un depart date dans le futur ferme le compte a cette date
            // (tache chauffeurs:cloturer-departs), pas des aujourd'hui.
            if (Carbon::parse($donnees['left_on'])->lte(today())) {
                $donnees['is_available'] = false;
                $driver->user?->forceFill(['is_active' => false])->save();
            }
        } elseif ($driver->left_on !== null) {
            // Depart annule : le motif part avec la date. Le compte ne
            // retrouve son acces que si c'est ce depart qui l'avait ferme ;
            // un compte ferme a part depuis l'ecran Personnel le reste.
            $donnees['departure_reason'] = null;

            if ($driver->left_on->lte(today())) {
                $driver->user?->forceFill(['is_active' => true])->save();
            }
        }

        // Seul un vrai changement se refuse : enregistrer la fiche d'un
        // chauffeur deja hors service, ou jamais certifie, ne doit pas etre
        // bloque.
        $retireDuService = $donnees['is_available'] === false && $driver->is_available;
        $retireAdr = $donnees['adr_certified'] === false && $driver->adr_certified;

        if ($retireDuService || $retireAdr) {
            $encours = TransportOrder::whereIn('status', TransportOrder::ACTIFS)
                ->where('driver_id', $driver->id);

            if ($retireDuService && empty($donnees['left_on']) && (clone $encours)->exists()) {
                return back()->withErrors([
                    'is_available' => Traductions::t('msg.chauffeur_engage_service', 'Ce chauffeur porte une mission en cours : réaffectez-la depuis l\'écran Planification avant de le retirer du service.'),
                ]);
            }

            if ($retireAdr && (clone $encours)->where('is_hazardous', true)->exists()) {
                return back()->withErrors([
                    'adr_certified' => Traductions::t('msg.chauffeur_engage_adr', 'Ce chauffeur transporte une matière dangereuse : sa certification ADR ne peut pas être retirée maintenant.'),
                ]);
            }
        }

        $driver->update($donnees);

        // Le journal dit qui a modifie la fiche, pas son contenu RH : ni
        // le motif de sortie dans la phrase, ni Driver::DONNEES_RH dans le
        // detail. Ces valeurs restent sur la fiche, qui s'efface un an
        // apres le depart (chauffeurs:cloturer-departs).
        ActivityLog::record(
            ! empty($donnees['left_on']) ? 'driver.left' : 'driver.updated',
            ! empty($donnees['left_on'])
                ? 'Départ de '.trim($driver->user?->first_name.' '.$driver->user?->last_name)
                : 'Chauffeur '.trim($driver->user?->first_name.' '.$driver->user?->last_name).' mis à jour',
            $driver,
            Arr::except($donnees, Driver::DONNEES_RH),
        );

        $reponse = back()->with('success', ! empty($donnees['left_on'])
            ? Traductions::t('msg.depart_enregistre', 'Départ enregistré. La fiche est conservée pour l\'historique.')
            : Traductions::t('msg.chauffeur_mis_a_jour', 'Chauffeur mis à jour.'));

        // Une echeance corrigee peut rendre non conforme une mission deja
        // affectee : le planificateur l'apprend ici, et sur la carte.
        $aReaffecter = ControleAffectation::missionsNonConformes(TransportOrder::where('driver_id', $driver->id));

        return $aReaffecter === [] ? $reponse : $reponse->with('error', Traductions::t('msg.missions_a_reaffecter', 'Attention : ces missions ne sont plus conformes et doivent être réaffectées : :missions.', [
            'missions' => implode(', ', $aReaffecter),
        ]));
    }

    /**
     * Statuts d'emploi dans la langue de l'utilisateur.
     *
     * @return array<string, string>
     */
    public static function statuts(): array
    {
        return collect(Driver::STATUTS)
            ->map(fn (string $libelle, string $code) => Traductions::t('chauffeurs.statut_'.strtolower($code), $libelle))
            ->all();
    }

    /**
     * Motifs de sortie dans la langue de l'utilisateur.
     *
     * @return array<string, string>
     */
    public static function motifsSortie(): array
    {
        return collect(Driver::MOTIFS_SORTIE)
            ->map(fn (string $libelle, string $code) => Traductions::t('chauffeurs.sortie_'.strtolower($code), $libelle))
            ->all();
    }

    /**
     * « Prenom Nom » des comptes dont le prenom, le nom ou l'adresse
     * contient le texte tape.
     *
     * @param  callable(): Builder  $comptes
     * @return list<string>
     */
    public static function noms(callable $comptes, ?string $terme): array
    {
        $terme = trim((string) $terme);

        if (mb_strlen($terme) < 2) {
            return [];
        }

        $trouves = $comptes()
            ->where(fn ($q) => $q->whereNomContient($terme)->orWhereContient('email', $terme))
            ->limit(20)
            ->get(['first_name', 'last_name', 'email']);

        return Suggestions::ranger($trouves->map(fn ($u) => str_contains(mb_strtolower($u->email), mb_strtolower($terme)) && ! str_contains(mb_strtolower($u->first_name.' '.$u->last_name), mb_strtolower($terme))
            ? $u->email
            : trim($u->first_name.' '.$u->last_name))->all());
    }
}
