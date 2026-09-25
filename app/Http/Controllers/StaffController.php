<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public const ROLES = [
        'DRIVER' => 'Chauffeur',
        'PLANNER' => 'Planificateur',
        'ADMIN' => 'Administrateur',
    ];

    /**
     * Roles dans la langue de l'utilisateur.
     *
     * @return array<string, string>
     */
    private static function roles(): array
    {
        return [
            'DRIVER' => Traductions::t('roles.driver', self::ROLES['DRIVER']),
            'PLANNER' => Traductions::t('roles.planner', self::ROLES['PLANNER']),
            'ADMIN' => Traductions::t('roles.administrateur', self::ROLES['ADMIN']),
        ];
    }

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
                        'role' => self::roles()[$u->role] ?? $u->role,
                        'role_code' => $u->role,
                        'actif' => (bool) $u->is_active,
                        'confirme' => $u->email_verified_at !== null,
                        'soi_meme' => $u->id === $request->user()->id,
                        'permis' => $chauffeur?->license_type,
                        'empechements' => $chauffeur?->empechements() ?? [],
                        'sorti_le' => $chauffeur?->left_on?->format('d/m/Y'),
                    ];
                })->all(),
            'roles' => self::roles(),
            'permis' => ['C', 'CE', 'C1', 'C1E'],
            'statuts' => DriverController::statuts(),
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
            'email.unique' => Traductions::t('msg.email_deja_utilise', 'Cette adresse est déjà utilisée par un compte.'),
            'license_number.unique' => Traductions::t('msg.permis_deja_enregistre', 'Ce numéro de permis est déjà enregistré.'),
            'license_expiry.after' => Traductions::t('msg.permis_expire', 'Un permis déjà expiré ne permet pas de créer le compte.'),
            'required_if' => Traductions::t('msg.champ_requis_chauffeur', 'Ce champ est obligatoire pour un chauffeur.'),
        ]);

        $utilisateur = DB::transaction(function () use ($donnees) {
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

        // Le compte existe deja : une panne du serveur de courriel ne doit
        // pas se changer en erreur cinq cents, qui laisserait croire a un
        // echec et bloquerait le nouvel essai sur « adresse deja utilisee ».
        $envoye = $this->envoyerLien($utilisateur);

        ActivityLog::record(
            'staff.created',
            'Compte '.mb_strtolower(self::ROLES[$utilisateur->role]).' créé pour '.trim($utilisateur->first_name.' '.$utilisateur->last_name),
            $utilisateur,
            ['role' => $utilisateur->role, 'email' => $utilisateur->email],
        );

        return $envoye
            ? back()->with('success', Traductions::t(
                'msg.compte_cree',
                'Compte créé. Un lien pour choisir le mot de passe vient d\'être envoyé à :email.',
                ['email' => $utilisateur->email],
            ))
            : back()->with('error', Traductions::t(
                'msg.compte_cree_sans_courriel',
                'Compte créé, mais le courriel n\'a pas pu partir. Renvoyez le lien depuis la liste.',
            ));
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_if(! array_key_exists($user->role, self::ROLES), 404);

        if ($user->id === $request->user()->id) {
            return back()->withErrors([
                'is_active' => Traductions::t('msg.desactiver_soi_meme', 'Vous ne pouvez pas désactiver votre propre compte.'),
            ]);
        }

        if ($user->is_active && $user->isDriver()) {
            $engage = TransportOrder::whereIn('status', TransportOrder::ACTIFS)
                ->where('driver_id', $user->id)
                ->exists();

            if ($engage) {
                return back()->withErrors([
                    'is_active' => Traductions::t('msg.chauffeur_engage_compte', 'Ce chauffeur porte une mission en cours : réaffectez-la avant de fermer son compte.'),
                ]);
            }
        }

        if ($user->is_active && $user->isAdmin()
            && User::where('role', 'ADMIN')->where('is_active', true)->count() <= 1) {
            return back()->withErrors([
                'is_active' => Traductions::t('msg.dernier_admin', 'C\'est le dernier administrateur actif : nommez-en un autre avant de fermer celui-ci.'),
            ]);
        }

        $user->update(['is_active' => ! $user->is_active]);

        if (! $user->is_active) {
            $this->couperLesAcces($user);
        }

        ActivityLog::record(
            $user->is_active ? 'staff.enabled' : 'staff.disabled',
            'Compte de '.trim($user->first_name.' '.$user->last_name).($user->is_active ? ' réactivé' : ' désactivé'),
            $user,
            ['role' => $user->role],
        );

        return back()->with('success', $user->is_active
            ? Traductions::t('msg.compte_reactive', 'Compte réactivé.')
            : Traductions::t('msg.compte_ferme', 'Compte désactivé.'));
    }

    private function envoyerLien(User $user): bool
    {
        try {
            Password::sendResetLink(['email' => $user->email]);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function couperLesAcces(User $user): void
    {
        $user->forceFill(['remember_token' => null])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }
    }

    public function resetLink(User $user): RedirectResponse
    {
        abort_if(! array_key_exists($user->role, self::ROLES), 404);

        if (! $this->envoyerLien($user)) {
            return back()->with('error', Traductions::t('msg.courriel_echec', 'Le courriel n\'a pas pu partir. Réessayez dans quelques minutes.'));
        }

        ActivityLog::record(
            'staff.reset_link',
            'Lien de mot de passe renvoyé à '.$user->email,
            $user,
        );

        return back()->with('success', Traductions::t('msg.lien_envoye', 'Lien envoyé à :email.', ['email' => $user->email]));
    }
}
