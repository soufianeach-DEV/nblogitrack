<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Traductions;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'peutSupprimer' => $request->user()->role === 'CLIENT',
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $ancienneAdresse = $request->user()->email;

        $request->user()->fill($request->safe()->except('current_password'));

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        // Les factures partent au contact principal de l'entreprise. Quand
        // ce contact, c'est ce compte, son adresse et son nom suivent :
        // sinon les factures continuaient vers l'ancienne adresse.
        if ($request->user()->isClient() && $request->user()->client_id !== null) {
            ClientContact::where('client_id', $request->user()->client_id)
                ->where('is_primary', true)
                ->whereRaw('lower(email) = ?', [mb_strtolower((string) $ancienneAdresse)])
                ->update([
                    'email' => $request->user()->email,
                    'first_name' => $request->user()->first_name,
                    'last_name' => $request->user()->last_name,
                ]);
        }

        return Redirect::route('profile.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Les comptes du personnel se ferment depuis l'ecran Personnel, qui
        // refuse de retirer le dernier administrateur ou un chauffeur en
        // pleine mission. Les supprimer d'ici contournait ces gardes.
        abort_unless($user->role === 'CLIENT', 403);

        $collegues = User::where('client_id', $user->client_id)->where('id', '!=', $user->id);

        // L'entreprise ne se retrouve pas sans administrateur : il faut en
        // designer un autre avant de partir.
        if ($user->company_role === 'ADMIN'
            && (clone $collegues)->exists()
            && ! (clone $collegues)->where('company_role', 'ADMIN')->where('is_active', true)->exists()) {
            return back()->withErrors([
                'password' => Traductions::t('msg.dernier_admin_entreprise', 'Vous êtes le seul administrateur de votre entreprise : désignez-en un autre avant de supprimer votre compte.'),
            ]);
        }

        // Dernier compte de l'entreprise : l'entreprise part avec lui, sauf
        // si elle a deja transporte ou ete facturee. Elle garde alors ses
        // pieces, que la loi impose de conserver.
        $derniere = ! (clone $collegues)->exists();
        $historique = $derniere && $user->client_id !== null && (
            TransportOrder::where('client_id', $user->client_id)->exists()
            || Invoice::where('client_id', $user->client_id)->exists()
        );

        if ($historique) {
            return back()->withErrors([
                'password' => Traductions::t('msg.compte_non_supprimable', 'Votre entreprise a des expéditions ou des factures, que nous devons conserver. Écrivez-nous pour clôturer le compte.'),
            ]);
        }

        $entreprise = $derniere ? $user->client : null;

        Auth::logout();

        DB::transaction(function () use ($user, $entreprise) {
            $user->delete();
            $entreprise?->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
