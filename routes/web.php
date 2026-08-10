<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\TransportOrderController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

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

    Route::middleware('can:plan-orders')->group(function () {
        Route::get('/planification', [PlanningController::class, 'index'])->name('planning.index');
        Route::post('/planification/{transportOrder}/affectation', [PlanningController::class, 'assign'])->name('planning.assign');
        Route::patch('/planification/{transportOrder}/statut', [PlanningController::class, 'updateStatus'])->name('planning.status');
    });
});

Route::get('/suivi', [TrackingController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('tracking.show');

Route::middleware('throttle:120,1')->group(function () {
    Route::get('/geo/villes', [GeoController::class, 'villes'])->name('geo.villes');
    Route::get('/geo/codes-postaux', [GeoController::class, 'codesPostaux'])->name('geo.codes-postaux');
    Route::get('/geo/numeros', [GeoController::class, 'numeros'])->name('geo.numeros');
});

require __DIR__.'/auth.php';
