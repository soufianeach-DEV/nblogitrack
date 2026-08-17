<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VatController;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use App\Support\IdentifiantEntreprise;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    private const FONCTIONS_METIER = [
        'Acheteur',
        'Administrateur',
        'Administrateur délégué',
        'Administrateur gérant',
        'Affréteur',
        'Assistant administratif',
        'Assistant logistique',
        'Associé',
        'Chargé de clientèle',
        'Chef d\'entreprise',
        'Chef d\'équipe',
        'Comptable',
        'Déclarant en douane',
        'Directeur commercial',
        'Directeur des opérations',
        'Directeur financier',
        'Directeur général',
        'Directeur logistique',
        'Dispatcher',
        'Fondateur',
        'Gérant',
        'Exploitant transport',
        'Gestionnaire de flotte',
        'Gestionnaire de stock',
        'Magasinier',
        'Planificateur transport',
        'Responsable achats',
        'Responsable entrepôt',
        'Responsable expéditions',
        'Responsable logistique',
        'Responsable qualité',
        'Responsable sécurité (HSE)',
        'Responsable transport',
        'Secrétaire de direction',
        'Travailleur indépendant',
    ];

    private const SECTEURS_METIER = [
        'Agriculture',
        'Agroalimentaire',
        'Automobile',
        'Aéronautique',
        'Bois et papier',
        'Chimie',
        'Construction',
        'Cosmétique',
        'Distribution',
        'E-commerce',
        'Emballage',
        'Énergie',
        'Grande distribution',
        'Logistique',
        'Machines et équipements',
        'Matériaux de construction',
        'Mobilier',
        'Métallurgie',
        'Pharmaceutique',
        'Plasturgie',
        'Recyclage',
        'Santé',
        'Textile',
        'Transport',
        'Électronique',
    ];

    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'secteurs' => $this->referentiel('clients', 'business_sector', self::SECTEURS_METIER),
            'fonctions' => $this->referentiel('client_contacts', 'position', self::FONCTIONS_METIER),
        ]);
    }

    /**
     * @param  array<int, string>  $metier
     * @return array<int, string>
     */
    private function referentiel(string $table, string $colonne, array $metier): array
    {
        $enBase = DB::table($table)->whereNotNull($colonne)->distinct()->pluck($colonne)->all();

        $valeurs = array_unique(array_merge($metier, $enBase));

        collator_sort(collator_create('fr_FR'), $valeurs);

        return array_values($valeurs);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function denominationDejaPrise(array $data): bool
    {
        return Client::whereRaw('lower(company_name) = ?', [mb_strtolower($data['company_name'])])
            ->where('country', $data['country'])
            ->whereRaw('lower(city) = ?', [mb_strtolower($data['city'])])
            ->where(function ($q) use ($data) {
                $secteur = $data['business_sector'] ?? null;

                $secteur === null
                    ? $q->whereNull('business_sector')
                    : $q->whereRaw('lower(business_sector) = ?', [mb_strtolower($secteur)]);
            })
            ->exists();
    }

    private function situationInterdite(string $tva): ?string
    {
        $verification = app(VatController::class)->verifier(
            Request::create('/verification-tva', 'GET', ['tva' => $tva])
        );

        $resultat = $verification->getData(true);
        $statut = $resultat['statut'] ?? '';

        if ($statut === 'invalide') {
            return 'Ce numéro n\'est pas actif dans le registre européen.';
        }

        if ($statut !== 'valide') {
            return 'Le registre européen est momentanément injoignable, la vérification est impossible. Réessayez dans quelques minutes.';
        }

        $situation = $resultat['entreprise']['situation'] ?? null;

        if ($situation !== null && $situation['acceptable'] === false) {
            return 'Situation juridique incompatible : '.$situation['libelle'].'. L\'inscription est refusée.';
        }

        return null;
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:150',
            'vat_number' => ['required', 'string', 'max:30', 'regex:/^([A-Z]{2}[0-9A-Z]{8,12}|\d{9}|\d{14})$/'],
            'billing_address' => 'required|string|max:255',
            'postal_code' => 'required|string|max:10',
            'city' => 'required|string|max:100',
            'country' => 'required|string|max:60',
            'business_sector' => 'nullable|string|max:100',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'position' => 'nullable|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'required|string|lowercase|email|max:150|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'marque_declaree' => 'accepted',
        ], [
            'vat_number.regex' => 'Saisis un numéro de TVA (ex. BE0123456789) ou un SIREN/SIRET français.',
            'billing_address.required' => 'Sélectionne l\'adresse du siège dans les listes proposées.',
            'marque_declaree.accepted' => 'Vous devez confirmer que la dénomination ne porte pas atteinte à une marque déposée.',
        ]);

        if ($this->denominationDejaPrise($data)) {
            return back()->withInput()->withErrors([
                'company_name' => 'Une entreprise portant ce nom est déjà enregistrée dans le même secteur et la même localité. Précisez la dénomination pour la distinguer.',
            ]);
        }

        $identifiants = IdentifiantEntreprise::analyser($data['vat_number']);
        $data['vat_number'] = $identifiants['tva'] ?? strtoupper($data['vat_number']);

        if (Client::where('vat_number', $data['vat_number'])->exists()) {
            return back()->withInput()->withErrors(['vat_number' => 'Ce numéro de TVA est déjà enregistré.']);
        }

        if ($message = $this->situationInterdite($data['vat_number'])) {
            return back()->withInput()->withErrors(['vat_number' => $message]);
        }

        $user = DB::transaction(function () use ($data, $identifiants) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'CLIENT',
                'is_active' => true,
            ]);

            Client::create([
                'id' => $user->id,
                'company_name' => $data['company_name'],
                'vat_number' => $data['vat_number'],
                'enterprise_number' => $identifiants['national'],
                'peppol_id' => $identifiants['peppol'],
                'billing_address' => $data['billing_address'],
                'postal_code' => $data['postal_code'],
                'city' => $data['city'],
                'country' => $data['country'],
                'business_sector' => $data['business_sector'] ?? null,
                'is_validated' => false,
            ]);

            ClientContact::create([
                'client_id' => $user->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'position' => $data['position'] ?? null,
                'is_primary' => true,
            ]);

            return $user;
        });

        event(new Registered($user));

        ActivityLog::record(
            'client.registered',
            'Demande d\'inscription de '.$data['company_name'].' ('.strtoupper($data['vat_number']).')',
            $user,
            ['entreprise' => $data['company_name'], 'tva' => strtoupper($data['vat_number'])],
            $user->id,
        );

        return redirect()->route('login')->with('status',
            'Votre demande est enregistrée. Un administrateur doit valider votre entreprise avant votre première connexion : vous recevrez un e-mail dès l\'activation.'
        );
    }
}
