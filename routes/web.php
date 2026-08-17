<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\ClientValidationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LangueController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PagePubliqueController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProcessingRecordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RechercheController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TarifController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\TransportOrderController;
use App\Http\Controllers\VatController;
use App\Http\Controllers\VehicleController;
use App\Support\Traductions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

        Route::get('/recherche', [RechercheController::class, 'suggestions'])
            ->middleware('throttle:60,1')
            ->name('recherche.suggestions');
        Route::get('/transport-orders/{transportOrder}', [TransportOrderController::class, 'show'])
            ->whereNumber('transportOrder')
            ->name('transport-orders.show');

        Route::middleware('can:plan-orders')->group(function () {
            Route::get('/planification', [PlanningController::class, 'index'])->name('planning.index');
            Route::post('/planification/{transportOrder}/affectation', [PlanningController::class, 'assign'])->name('planning.assign');
            Route::patch('/planification/{transportOrder}/statut', [PlanningController::class, 'updateStatus'])->name('planning.status');
            Route::post('/planification/{transportOrder}/desaffectation', [PlanningController::class, 'desaffecter'])->name('planning.desaffecter');

            Route::patch('/planification/{transportOrder}/suivi', [PlanningController::class, 'suiviDirect'])
                ->name('planning.tracking');
        });

        Route::middleware('can:handle-quotes')->group(function () {
            Route::get('/demandes-de-devis', [QuoteController::class, 'index'])->name('quotes.index');
            Route::patch('/demandes-de-devis/{quoteRequest}/statut', [QuoteController::class, 'updateStatus'])->name('quotes.status');
        });

        Route::middleware('can:drive')->group(function () {
            Route::get('/missions', [MissionController::class, 'index'])->name('missions.index');
            Route::patch('/missions/{transportOrder}/statut', [MissionController::class, 'updateStatus'])->name('missions.status');

            Route::post('/missions/{transportOrder}/position', [MissionController::class, 'position'])
                ->middleware('throttle:30,1')
                ->name('missions.position');

            Route::post('/missions/note', [MissionController::class, 'accuser'])->name('missions.notice');
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

        Route::post('/factures/{invoice}/payer', [PaymentController::class, 'payer'])
            ->whereNumber('invoice')
            ->name('payments.payer');
        Route::get('/factures/{invoice}/paiement/retour', [PaymentController::class, 'retour'])
            ->whereNumber('invoice')
            ->name('payments.retour');

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

        Route::middleware('can:manage-users')->group(function () {
            Route::get('/personnel', [StaffController::class, 'index'])->name('staff.index');
            Route::post('/personnel', [StaffController::class, 'store'])->name('staff.store');
            Route::patch('/personnel/{user}/activation', [StaffController::class, 'toggle'])->name('staff.toggle');
            Route::post('/personnel/{user}/lien-mot-de-passe', [StaffController::class, 'resetLink'])->name('staff.reset-link');

            Route::get('/traductions', [TranslationController::class, 'index'])->name('translations.index');
            Route::patch('/traductions/{translation}', [TranslationController::class, 'update'])->name('translations.update');

            Route::get('/api', [ApiKeyController::class, 'index'])->name('api-keys.index');
            Route::post('/api', [ApiKeyController::class, 'store'])->name('api-keys.store');
            Route::patch('/api/{apiKey}/revocation', [ApiKeyController::class, 'revoke'])->name('api-keys.revoke');

            Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
            Route::post('/pages', [PageController::class, 'store'])->name('pages.store');
            Route::patch('/pages/{page}', [PageController::class, 'update'])->name('pages.update');
            Route::patch('/pages/{page}/publication', [PageController::class, 'publier'])->name('pages.publish');
            Route::delete('/pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
            Route::post('/pages/{page}/envoi', [PageController::class, 'envoyerNote'])->name('pages.notice.send');

            Route::post('/pages/documents', [PageController::class, 'televerser'])->name('pages.documents.store');
            Route::delete('/pages/documents/{pageDocument}', [PageController::class, 'supprimerDocument'])
                ->name('pages.documents.destroy');

            Route::get('/registre', [ProcessingRecordController::class, 'index'])->name('registre.index');
            Route::patch('/registre/{processingRecord}', [ProcessingRecordController::class, 'update'])
                ->name('registre.update');
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

    Route::get('/p/{page:slug}', [PagePubliqueController::class, 'show'])->name('pages.show');

    require __DIR__.'/auth.php';

});

Route::middleware(['auth', 'throttle:itineraires'])->group(function () {
    Route::get('/suivi/{transportOrder}/itineraire', [TrackingController::class, 'itineraire'])
        ->name('tracking.itineraire');
    Route::get('/suivi/{transportOrder}/peages', [TrackingController::class, 'peages'])
        ->name('tracking.peages');
});

Route::middleware('throttle:120,1')->group(function () {
    Route::get('/geo/villes', [GeoController::class, 'villes'])->name('geo.villes');
    Route::get('/geo/codes-postaux', [GeoController::class, 'codesPostaux'])->name('geo.codes-postaux');
});

Route::get('/geo/numeros', [GeoController::class, 'numeros'])
    ->middleware('throttle:15,1')
    ->name('geo.numeros');

Route::post('/stripe/webhook', [PaymentController::class, 'webhook'])
    ->name('payments.webhook');

Route::get('/verification-tva', [VatController::class, 'verifier'])
    ->middleware('throttle:20,1')
    ->name('vat.verify');

Route::get('/documents/{pageDocument}', [PagePubliqueController::class, 'document'])
    ->whereNumber('pageDocument')
    ->name('pages.documents.show');

Route::get('/langue/{vers}', LangueController::class)->name('langue');

Route::get('/', fn () => redirect('/'.app()->getLocale()));

Route::fallback(function (Request $requete) {
    $chemin = trim($requete->path(), '/');

    abort_if(Traductions::estServie(explode('/', $chemin)[0]), 404);

    return redirect('/'.app()->getLocale().'/'.$chemin.
        ($requete->getQueryString() ? '?'.$requete->getQueryString() : ''));
});
