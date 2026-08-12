<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ClientValidationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\TransportOrderController;
use App\Http\Controllers\VatController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

    Route::get('/journal', [ActivityLogController::class, 'index'])
        ->middleware('can:view-logs')
        ->name('activity-logs.index');

    Route::middleware('can:validate-clients')->group(function () {
        Route::get('/entreprises', [ClientValidationController::class, 'index'])->name('clients.index');
        Route::post('/entreprises/{client}/validation', [ClientValidationController::class, 'approve'])->name('clients.approve');
        Route::post('/entreprises/{client}/refus', [ClientValidationController::class, 'reject'])->name('clients.reject');
    });
});

Route::get('/devis', [QuoteController::class, 'create'])->name('devis.create');
Route::post('/devis', [QuoteController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('devis.store');
Route::get('/devis/confirmation', [QuoteController::class, 'confirmation'])->name('devis.confirmation');

Route::get('/suivi', [TrackingController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('tracking.show');

// L'itineraire interroge deux services exterieurs : il reste derriere le
// compte, et le controleur verifie en plus que l'expedition est consultable.
Route::middleware(['auth', 'throttle:60,1'])->group(function () {
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

Route::get('/verification-tva', [VatController::class, 'verifier'])
    ->middleware('throttle:20,1')
    ->name('vat.verify');

require __DIR__.'/auth.php';
