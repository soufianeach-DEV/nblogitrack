<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ClientValidationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TarifController;
use App\Http\Controllers\LangueController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\TransportOrderController;
use App\Http\Controllers\VatController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * Les pages portent la langue dans leur adresse : /fr/tarifs, /nl/tarifs.
 * Chaque version a ainsi sa propre URL, partageable et indexable, ce qu'un
 * choix garde en session ne permet pas.
 *
 * Les points d'entree JSON restent hors du prefixe, plus bas : une liste de
 * codes postaux n'a pas de version neerlandaise, et les prefixer obligerait
 * le JavaScript a connaitre la langue courante pour appeler le serveur.
 */
Route::prefix('{langue}')->whereIn('langue', ['fr', 'nl', 'en'])->group(function () {

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('accueil');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/transport-orders/create', [TransportOrderController::class, 'create'])
        ->name('transport-orders.create');
    Route::post('/transport-orders', [TransportOrderController::class, 'store'])
        ->name('transport-orders.store');
    Route::get('/transport-orders', [TransportOrderController::class, 'index'])
        ->name('transport-orders.index');
    Route::get('/transport-orders/{transportOrder}', [TransportOrderController::class, 'show'])
        ->whereNumber('transportOrder')
        ->name('transport-orders.show');

    Route::middleware('can:plan-orders')->group(function () {
        Route::get('/planification', [PlanningController::class, 'index'])->name('planning.index');
        Route::post('/planification/{transportOrder}/affectation', [PlanningController::class, 'assign'])->name('planning.assign');
        Route::patch('/planification/{transportOrder}/statut', [PlanningController::class, 'updateStatus'])->name('planning.status');
    });

    Route::middleware('can:handle-quotes')->group(function () {
        Route::get('/demandes-de-devis', [QuoteController::class, 'index'])->name('quotes.index');
        Route::patch('/demandes-de-devis/{quoteRequest}/statut', [QuoteController::class, 'updateStatus'])->name('quotes.status');
    });

    Route::middleware('can:drive')->group(function () {
        Route::get('/missions', [MissionController::class, 'index'])->name('missions.index');
        Route::patch('/missions/{transportOrder}/statut', [MissionController::class, 'updateStatus'])->name('missions.status');
    });

    Route::get('/factures', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/factures/{invoice}', [InvoiceController::class, 'show'])
        ->whereNumber('invoice')
        ->name('invoices.show');
    Route::patch('/factures/{invoice}/paiement', [InvoiceController::class, 'markPaid'])
        ->middleware('can:control-payments')
        ->name('invoices.paid');
    Route::get('/factures/{invoice}/pdf', [InvoiceController::class, 'pdf'])
        ->whereNumber('invoice')
        ->name('invoices.pdf');
    Route::get('/factures/{invoice}/ubl', [InvoiceController::class, 'ubl'])
        ->whereNumber('invoice')
        ->name('invoices.ubl');

    // F6 : le client regle sa facture en ligne. Le retour du navigateur
    // n'ecrit rien, il informe ; c'est le webhook qui fait foi.
    Route::post('/factures/{invoice}/payer', [PaymentController::class, 'payer'])
        ->whereNumber('invoice')
        ->name('payments.payer');
    Route::get('/factures/{invoice}/paiement/retour', [PaymentController::class, 'retour'])
        ->whereNumber('invoice')
        ->name('payments.retour');

    // Les achats sont la comptabilite interne : ils ne sortent pas du perimetre
    // de l'administrateur, comme le controle des paiements.
    Route::middleware('can:control-payments')->group(function () {
        Route::get('/achats', [PurchaseInvoiceController::class, 'index'])->name('purchases.index');
        Route::post('/achats', [PurchaseInvoiceController::class, 'store'])->name('purchases.store');
        Route::patch('/achats/{purchaseInvoice}/paiement', [PurchaseInvoiceController::class, 'markPaid'])
            ->name('purchases.paid');
        Route::get('/tva', [PurchaseInvoiceController::class, 'tva'])->name('purchases.tva');
    });

    Route::middleware('can:view-fleet')->group(function () {
        Route::get('/vehicules', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('/chauffeurs', [DriverController::class, 'index'])->name('drivers.index');
    });

    Route::middleware('can:manage-fleet')->group(function () {
        Route::patch('/vehicules/{vehicle:registration}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::patch('/chauffeurs/{driver}', [DriverController::class, 'update'])->name('drivers.update');
    });

    // A2 : les comptes du personnel. Le client s'inscrit lui-meme, le
    // chauffeur, le planificateur et l'administrateur sont crees ici.
    Route::middleware('can:manage-users')->group(function () {
        Route::get('/personnel', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/personnel', [StaffController::class, 'store'])->name('staff.store');
        Route::patch('/personnel/{user}/activation', [StaffController::class, 'toggle'])->name('staff.toggle');
        Route::post('/personnel/{user}/lien-mot-de-passe', [StaffController::class, 'resetLink'])->name('staff.reset-link');

        // A11 : corriger un texte sans toucher au code ni redeployer.
        Route::get('/traductions', [TranslationController::class, 'index'])->name('translations.index');
        Route::patch('/traductions/{translation}', [TranslationController::class, 'update'])->name('translations.update');
    });

    Route::get('/journal', [ActivityLogController::class, 'index'])
        ->middleware('can:view-logs')
        ->name('activity-logs.index');

    Route::middleware('can:validate-clients')->group(function () {
        Route::get('/entreprises', [ClientValidationController::class, 'index'])->name('clients.index');
        Route::post('/entreprises/{client}/validation', [ClientValidationController::class, 'approve'])->name('clients.approve');
        Route::post('/entreprises/{client}/refus', [ClientValidationController::class, 'reject'])->name('clients.reject');
    });
});

// F3 : consulter les tarifs sans compte. Le calcul reste au serveur, la
// grille ne part jamais vers un visiteur anonyme.
Route::get('/tarifs', [TarifController::class, 'index'])->name('tarifs.index');
Route::post('/tarifs/simulation', [TarifController::class, 'simuler'])
    ->middleware('throttle:20,1')
    ->name('tarifs.simuler');

Route::get('/devis', [QuoteController::class, 'create'])->name('devis.create');
Route::post('/devis', [QuoteController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('devis.store');
Route::get('/devis/confirmation', [QuoteController::class, 'confirmation'])->name('devis.confirmation');

Route::get('/suivi', [TrackingController::class, 'show'])
    ->middleware('throttle:suivi')
    ->name('tracking.show');

require __DIR__.'/auth.php';

}); // fin du groupe prefixe par la langue

/*
 * Ce qui suit ne rend pas de page : ni prefixe, ni traduit.
 */

// L'itineraire interroge deux services exterieurs : il reste derriere le
// compte, et le controleur verifie en plus que l'expedition est consultable.
Route::middleware(['auth', 'throttle:itineraires'])->group(function () {
    Route::get('/suivi/{transportOrder}/itineraire', [TrackingController::class, 'itineraire'])
        ->name('tracking.itineraire');
    Route::get('/suivi/{transportOrder}/peages', [TrackingController::class, 'peages'])
        ->name('tracking.peages');
});

Route::middleware('throttle:120,1')->group(function () {
    Route::get('/geo/villes', [GeoController::class, 'villes'])->name('geo.villes');
    Route::get('/geo/codes-postaux', [GeoController::class, 'codesPostaux'])->name('geo.codes-postaux');
    Route::get('/geo/numeros', [GeoController::class, 'numeros'])->name('geo.numeros');
});

// Stripe appelle cette adresse depuis ses serveurs : pas de session, donc
// pas de jeton CSRF. L'exception est declaree dans bootstrap/app.php et
// c'est la signature de l'en-tete qui authentifie l'appel.
Route::post('/stripe/webhook', [PaymentController::class, 'webhook'])
    ->name('payments.webhook');

Route::get('/verification-tva', [VatController::class, 'verifier'])
    ->middleware('throttle:20,1')
    ->name('vat.verify');

// F11 : la bascule est ouverte au visiteur. Un client neerlandophone
// doit pouvoir lire la page d'accueil dans sa langue avant de creer un
// compte, pas apres.
//
// Le parametre s'appelle « vers » et non « langue » : URL::defaults pose
// une valeur par defaut pour « langue », qui remplissait ce parametre
// homonyme et renvoyait les trois boutons vers la langue courante.
Route::get('/langue/{vers}', LangueController::class)->name('langue');

// Une adresse sans prefixe rejoint la langue en cours plutot que de
// renvoyer une erreur : les liens d'avant le prefixage continuent de
// fonctionner, et l'on n'atterrit jamais sur une page morte.
Route::get('/', fn () => redirect('/'.app()->getLocale()));

Route::fallback(function (Illuminate\Http\Request $requete) {
    $chemin = trim($requete->path(), '/');

    // Deja prefixe : c'est une vraie page introuvable, pas un oubli.
    abort_if(App\Support\Traductions::estServie(explode('/', $chemin)[0]), 404);

    return redirect('/'.app()->getLocale().'/'.$chemin.
        ($requete->getQueryString() ? '?'.$requete->getQueryString() : ''));
});
