<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'administrateur d'une entreprise cliente gere ses collegues : il les
 * invite, choisit leur role (administrateur, commandes, comptabilite) et
 * ferme leur acces. L'entreprise garde toujours un administrateur actif.
 */
class CompanyUserController extends Controller
{
    public function index(Request $request): Response
    {
        $moi = $request->user();

        return Inertia::render('Entreprise/Utilisateurs', [
            'entreprise' => $moi->client?->company_name,
            'utilisateurs' => User::where('client_id', $moi->client_id)
                ->orderBy('last_name')->orderBy('first_name')
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'nom' => trim($u->first_name.' '.$u->last_name),
                    'email' => $u->email,
                    'role' => $u->company_role,
                    'actif' => (bool) $u->is_active,
                    'moi' => $u->id === $moi->id,
                ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $moi = $request->user();

        $donnees = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email:rfc|max:255|unique:users,email',
            'role' => ['required', Rule::in(['ADMIN', 'ORDERS', 'BILLING'])],
        ], [
            'email.unique' => Traductions::t('msg.email_deja_utilise', 'Cette adresse e-mail est déjà utilisée.'),
        ]);

        $collegue = User::create([
            'first_name' => $donnees['first_name'],
            'last_name' => $donnees['last_name'],
            'email' => mb_strtolower($donnees['email']),
            // Un mot de passe aleatoire, jamais communique : le collegue
            // choisit le sien par le lien recu.
            'password' => Str::password(40),
            'role' => 'CLIENT',
            'client_id' => $moi->client_id,
            'company_role' => $donnees['role'],
            'locale' => $moi->locale ?: app()->getLocale(),
            'is_active' => true,
        ]);

        ActivityLog::record(
            'company.user_invited',
            'Invitation de '.$collegue->email.' dans '.$moi->client?->company_name,
            $collegue,
            ['role' => $collegue->company_role, 'entreprise_id' => $moi->client_id],
        );

        try {
            Password::sendResetLink(['email' => $collegue->email]);
            $envoye = true;
        } catch (\Throwable $e) {
            report($e);
            $envoye = false;
        }

        return back()->with($envoye ? 'success' : 'error', $envoye
            ? Traductions::t('msg.collegue_invite', ':email est invité : il choisit son mot de passe par le lien reçu.', ['email' => $collegue->email])
            : Traductions::t('msg.collegue_invite_sans_lien', 'Le compte de :email est créé, mais le courriel n\'est pas parti. Il peut utiliser « Mot de passe oublié ».', ['email' => $collegue->email]));
    }

    public function update(Request $request, User $utilisateur): RedirectResponse
    {
        $this->autoriser($request, $utilisateur);

        $donnees = $request->validate([
            'role' => ['sometimes', Rule::in(['ADMIN', 'ORDERS', 'BILLING'])],
            'actif' => 'sometimes|boolean',
        ]);

        $apres = [
            'company_role' => $donnees['role'] ?? $utilisateur->company_role,
            'is_active' => array_key_exists('actif', $donnees) ? (bool) $donnees['actif'] : $utilisateur->is_active,
        ];

        // Le verrou evite que deux administrateurs se retirent leurs
        // droits l'un a l'autre en meme temps.
        $refus = DB::transaction(function () use ($utilisateur, $apres) {
            User::where('client_id', $utilisateur->client_id)->lockForUpdate()->get();

            $resteAdmin = User::where('client_id', $utilisateur->client_id)
                ->where('id', '!=', $utilisateur->id)
                ->where('company_role', 'ADMIN')
                ->where('is_active', true)
                ->exists();

            if (! $resteAdmin && ($apres['company_role'] !== 'ADMIN' || ! $apres['is_active'])) {
                return Traductions::t('msg.dernier_admin_societe', 'L\'entreprise doit garder au moins un administrateur actif.');
            }

            $utilisateur->update($apres);

            return null;
        });

        if ($refus !== null) {
            return back()->with('error', $refus);
        }

        if (! $apres['is_active']) {
            $utilisateur->forceFill(['remember_token' => null])->save();
            DB::table(config('session.table', 'sessions'))->where('user_id', $utilisateur->id)->delete();
        }

        ActivityLog::record(
            'company.user_updated',
            'Droits de '.$utilisateur->email.' modifiés',
            $utilisateur,
            ['role' => $apres['company_role'], 'actif' => $apres['is_active']],
        );

        return back()->with('success', Traductions::t('msg.collegue_modifie', 'Droits enregistrés.'));
    }

    private function autoriser(Request $request, User $utilisateur): void
    {
        abort_unless(
            $utilisateur->isClient() && $utilisateur->client_id === $request->user()->client_id,
            404,
        );
    }
}
