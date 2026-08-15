<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    /** Les comptes que cet ecran gere : le client s'inscrit lui-meme. */
    public const ROLES = [
        'DRIVER' => 'Chauffeur',
        'PLANNER' => 'Planificateur',
        'ADMIN' => 'Administrateur',
    ];

    public function index(Request $request): Response
    {
        $filtres = $request->validate([
            'q' => 'nullable|string|max:60',
            'role' => 'nullable|in:'.implode(',', array_keys(self::ROLES)),
            'etat' => 'nullable|in:actifs,inactifs',
        ]);

        $requete = User::whereIn('role', array_keys(self::ROLES));

        if (! empty($filtres['q'])) {
            $terme = '%'.$filtres['q'].'%';
            $requete->where(fn ($q) => $q
                ->where('first_name', 'ilike', $terme)
                ->orWhere('last_name', 'ilike', $terme)
                ->orWhere('email', 'ilike', $terme));
        }

        if (! empty($filtres['role'])) {
            $requete->where('role', $filtres['role']);
        }

        match ($filtres['etat'] ?? null) {
            'actifs' => $requete->where('is_active', true),
            'inactifs' => $requete->where('is_active', false),
            default => null,
        };

        $chauffeurs = Driver::whereIn('id', (clone $requete)->pluck('id'))->get()->keyBy('id');

        return Inertia::render('Personnel/Index', [
            'comptes' => $requete->orderBy('last_name')->orderBy('first_name')->get()
                ->map(function (User $u) use ($chauffeurs, $request) {
                    $chauffeur = $chauffeurs->get($u->id);

                    return [
                        'id' => $u->id,
                        'nom' => trim($u->first_name.' '.$u->last_name),
                        'email' => $u->email,
                        'telephone' => $u->phone,
                        'role' => self::ROLES[$u->role] ?? $u->role,
                        'role_code' => $u->role,
                        'actif' => (bool) $u->is_active,
                        'confirme' => $u->email_verified_at !== null,
                        // Se desactiver soi-meme fermerait la porte de
                        // l'interieur : personne ne pourrait plus rouvrir.
                        'soi_meme' => $u->id === $request->user()->id,
                        'permis' => $chauffeur?->license_type,
                        'empechements' => $chauffeur?->empechements() ?? [],
                        'sorti_le' => $chauffeur?->left_on?->format('d/m/Y'),
                    ];
                })->all(),
            'roles' => self::ROLES,
            'permis' => ['C', 'CE', 'C1', 'C1E'],
            'statuts' => Driver::STATUTS,
            'compteurs' => [
                'total' => User::whereIn('role', array_keys(self::ROLES))->count(),
                'actifs' => User::whereIn('role', array_keys(self::ROLES))->where('is_active', true)->count(),
                'inactifs' => User::whereIn('role', array_keys(self::ROLES))->where('is_active', false)->count(),
            ],
            'filtres' => $filtres,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:'.implode(',', array_keys(self::ROLES)),
            'license_number' => 'required_if:role,DRIVER|nullable|string|max:50|unique:drivers,license_number',
            'license_type' => 'required_if:role,DRIVER|nullable|in:C,CE,C1,C1E',
            'license_expiry' => 'required_if:role,DRIVER|nullable|date|after:today',
            'employment_status' => 'required_if:role,DRIVER|nullable|in:'.implode(',', array_keys(Driver::STATUTS)),
            'hired_on' => 'nullable|date|before_or_equal:today',
        ], [
            'email.unique' => 'Cette adresse est déjà utilisée par un compte.',
            'license_number.unique' => 'Ce numéro de permis est déjà enregistré.',
            'license_expiry.after' => 'Un permis déjà expiré ne permet pas de créer le compte.',
            'required_if' => 'Ce champ est obligatoire pour un chauffeur.',
        ]);

        $utilisateur = DB::transaction(function () use ($donnees) {
            // Aucun mot de passe n'est choisi ici : une valeur aleatoire
            // inutilisable ouvre le compte, et l'interesse definit le sien
            // par le lien de reinitialisation. Personne d'autre ne le connait.
            $utilisateur = User::create([
                'first_name' => $donnees['first_name'],
                'last_name' => $donnees['last_name'],
                'email' => $donnees['email'],
                'phone' => $donnees['phone'] ?? null,
                'role' => $donnees['role'],
                'password' => Str::random(48),
                'is_active' => true,
            ]);

            if ($donnees['role'] === 'DRIVER') {
                Driver::create([
                    'id' => $utilisateur->id,
                    'license_number' => $donnees['license_number'],
                    'license_type' => $donnees['license_type'],
                    'license_expiry' => $donnees['license_expiry'],
                    'employment_status' => $donnees['employment_status'],
                    'hired_on' => $donnees['hired_on'] ?? now()->toDateString(),
                    'is_available' => true,
                    'adr_certified' => false,
                    'daily_driving_hours' => 0,
                ]);
            }

            return $utilisateur;
        });

        Password::sendResetLink(['email' => $utilisateur->email]);

        ActivityLog::record(
            'staff.created',
            'Compte '.mb_strtolower(self::ROLES[$utilisateur->role]).' créé pour '.trim($utilisateur->first_name.' '.$utilisateur->last_name),
            $utilisateur,
            ['role' => $utilisateur->role, 'email' => $utilisateur->email],
        );

        return back()->with('success',
            'Compte créé. Un lien pour choisir le mot de passe vient d\'être envoyé à '.$utilisateur->email.'.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_if(! array_key_exists($user->role, self::ROLES), 404);

        if ($user->id === $request->user()->id) {
            return back()->withErrors([
                'is_active' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ]);
        }

        // Un compte qui porte une mission en cours ne se ferme pas : le
        // chauffeur ne pourrait plus la declarer livree.
        if ($user->is_active && $user->isDriver()) {
            $engage = TransportOrder::whereIn('status', ['PENDING', 'IN_PROGRESS'])
                ->where('driver_id', $user->id)
                ->exists();

            if ($engage) {
                return back()->withErrors([
                    'is_active' => 'Ce chauffeur porte une mission en cours : réaffectez-la avant de fermer son compte.',
                ]);
            }
        }

        // Le dernier administrateur actif ne se desactive pas : plus personne
        // ne pourrait rouvrir un compte ni en creer un.
        if ($user->is_active && $user->isAdmin()
            && User::where('role', 'ADMIN')->where('is_active', true)->count() <= 1) {
            return back()->withErrors([
                'is_active' => 'C\'est le dernier administrateur actif : nommez-en un autre avant de fermer celui-ci.',
            ]);
        }

        $user->update(['is_active' => ! $user->is_active]);

        ActivityLog::record(
            $user->is_active ? 'staff.enabled' : 'staff.disabled',
            'Compte de '.trim($user->first_name.' '.$user->last_name).($user->is_active ? ' réactivé' : ' désactivé'),
            $user,
            ['role' => $user->role],
        );

        return back()->with('success', $user->is_active ? 'Compte réactivé.' : 'Compte désactivé.');
    }

    /** Renvoyer le lien quand le premier a expire ou s'est perdu. */
    public function resetLink(User $user): RedirectResponse
    {
        abort_if(! array_key_exists($user->role, self::ROLES), 404);

        Password::sendResetLink(['email' => $user->email]);

        ActivityLog::record(
            'staff.reset_link',
            'Lien de mot de passe renvoyé à '.$user->email,
            $user,
        );

        return back()->with('success', 'Lien envoyé à '.$user->email.'.');
    }
}
