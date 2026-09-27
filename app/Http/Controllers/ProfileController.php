<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\ActivityLog;
use App\Models\ClientContact;
use App\Models\DriverAcknowledgement;
use App\Models\Invoice;
use App\Models\ShipmentPosition;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Traductions;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
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

        $nouvelleAdresse = $request->user()->isDirty('email');

        if ($nouvelleAdresse) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        // La nouvelle adresse doit etre confirmee avant tout autre ecran :
        // le lien part tout de suite.
        if ($nouvelleAdresse) {
            ActivityLog::record(
                'profile.email_changed',
                'Adresse du compte changée : '.$ancienneAdresse.' → '.$request->user()->email,
                $request->user(),
                ['avant' => $ancienneAdresse, 'apres' => $request->user()->email],
            );

            try {
                $request->user()->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                report($e);
            }
        }

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

    /**
     * Portabilite (RGPD, art. 20) : les donnees du compte, et pour un
     * responsable de l'entreprise celles de l'entreprise, dans un fichier
     * JSON lisible par une autre application.
     */
    public function exporter(Request $request): JsonResponse
    {
        $user = $request->user();
        $donnees = [
            'exporte_le' => now()->toIso8601String(),
            'compte' => $user->only(['first_name', 'last_name', 'email', 'phone', 'role', 'company_role', 'locale', 'created_at']),
        ];

        $client = $user->client;

        if ($client !== null) {
            $donnees['entreprise'] = $client->only(['company_name', 'vat_number', 'enterprise_number', 'peppol_id', 'billing_address', 'postal_code', 'city', 'country', 'business_sector', 'conditions_acceptees_le']);
            $donnees['contacts'] = $client->contacts()->get(['first_name', 'last_name', 'position', 'email', 'phone'])->toArray();

            if ($user->peutCommander() || $user->voitFacturesEntreprise()) {
                $donnees['expeditions'] = TransportOrder::where('client_id', $client->id)->orderBy('id')
                    ->get(['tracking_number', 'status', 'pickup_address', 'delivery_address', 'pickup_date', 'requested_delivery_date', 'actual_delivery_date', 'weight', 'volume', 'goods_type', 'estimated_cost', 'created_date'])
                    ->toArray();
            }

            if ($user->voitFacturesEntreprise()) {
                $donnees['factures'] = $client->invoices()->orderBy('id')
                    ->get(['reference', 'type', 'issued_on', 'due_on', 'amount_excl_tax', 'vat_amount', 'amount_incl_tax', 'status', 'paid_on'])
                    ->toArray();
            }
        }

        // Un chauffeur a aussi sa fiche (permis, visite medicale), les
        // positions relevees pendant ses missions, ses prises de
        // connaissance de la note et ses missions : l'export ne donnait que
        // son compte (article 15 du RGPD).
        if ($fiche = $user->driver) {
            $donnees['fiche_chauffeur'] = $fiche->only([
                'employment_status', 'hired_on', 'birth_date', 'retirement_planned_on',
                'license_number', 'license_type', 'license_expiry', 'cpc_expiry', 'tacho_card_expiry',
                'adr_certified', 'adr_expiry', 'medical_exam_date', 'left_on', 'departure_reason',
            ]);
            $donnees['missions'] = TransportOrder::where('driver_id', $fiche->id)->orderBy('id')
                ->get(['tracking_number', 'status', 'pickup_address', 'delivery_address', 'pickup_date', 'picked_up_at', 'delivered_at', 'received_by', 'delivery_reserves'])
                ->toArray();
            $donnees['indisponibilites'] = $fiche->indisponibilites()->orderBy('du')->get(['du', 'au', 'motif', 'commentaire'])->toArray();
        }

        $positions = ShipmentPosition::where('driver_id', $user->id)->orderBy('recorded_at')
            ->get(['transport_order_id', 'type', 'evenement', 'lat', 'lng', 'precision_m', 'recorded_at']);

        if ($positions->isNotEmpty()) {
            $donnees['positions'] = $positions->toArray();
        }

        $accuses = DriverAcknowledgement::where('user_id', $user->id)->orderBy('acknowledged_at')
            ->get(['version', 'acknowledged_at', 'ip_address']);

        if ($accuses->isNotEmpty()) {
            $donnees['prises_de_connaissance'] = $accuses->toArray();
        }

        ActivityLog::record('profile.exported', 'Export des données du compte '.$user->email, $user);

        return response()->json($donnees, 200, [
            'Content-Disposition' => 'attachment; filename="nblogitrack-donnees-'.now()->format('Y-m-d').'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

        // Les comptes de l'entreprise sont verrouilles le temps de la
        // verification : deux administrateurs qui partaient en meme temps
        // passaient chacun le controle et laissaient l'entreprise sans
        // administrateur.
        $refus = DB::transaction(function () use ($user) {
            if ($user->client_id !== null) {
                User::where('client_id', $user->client_id)->lockForUpdate()->get();
            }

            $collegues = User::where('client_id', $user->client_id)->where('id', '!=', $user->id);

            // L'entreprise ne se retrouve pas sans administrateur : il faut en
            // designer un autre avant de partir.
            if ($user->company_role === 'ADMIN'
                && (clone $collegues)->exists()
                && ! (clone $collegues)->where('company_role', 'ADMIN')->where('is_active', true)->exists()) {
                return Traductions::t('msg.dernier_admin_entreprise', 'Vous êtes le seul administrateur de votre entreprise : désignez-en un autre avant de supprimer votre compte.');
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
                return Traductions::t('msg.compte_non_supprimable', 'Votre entreprise a des expéditions ou des factures, que nous devons conserver. Écrivez-nous pour clôturer le compte.');
            }

            $entreprise = $derniere ? $user->client : null;

            ActivityLog::record(
                'profile.deleted',
                'Suppression du compte '.$user->email.($entreprise ? ' et de l\'entreprise '.$entreprise->company_name : ''),
                null,
                ['email' => $user->email, 'entreprise_id' => $user->client_id],
            );

            // Deconnexion avant la suppression : elle renouvelle le jeton
            // « se souvenir de moi » et reenregistrerait le compte.
            Auth::logout();

            $user->delete();
            $entreprise?->delete();

            return null;
        });

        if ($refus !== null) {
            return back()->withErrors(['password' => $refus]);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
