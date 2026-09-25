<?php

namespace App\Http\Controllers;

use App\Support\Traductions;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Invoice;
use App\Models\TransportOrder;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $request->user()->fill($request->safe()->except('current_password'));

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

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

        // Une entreprise qui a deja transporte ou ete facturee garde ses
        // pieces : la loi impose de conserver les factures, et les cles
        // etrangeres refusaient de toute facon la suppression, par une
        // erreur cinq cents apres avoir deja deconnecte l'utilisateur.
        $historique = TransportOrder::where('client_id', $user->id)->exists()
            || Invoice::where('client_id', $user->id)->exists();

        if ($historique) {
            return back()->withErrors([
                'password' => Traductions::t('msg.compte_non_supprimable', 'Votre entreprise a des expéditions ou des factures, que nous devons conserver. Écrivez-nous pour clôturer le compte.'),
            ]);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
