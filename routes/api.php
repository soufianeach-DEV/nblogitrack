<?php

use App\Http\Controllers\Api\DocumentationController;
use App\Http\Controllers\Api\ExpeditionController;
use Illuminate\Support\Facades\Route;

// La documentation de l'API, lisible dans Swagger UI. Le meme domaine que
// l'API : le bouton « Try it out » y envoie de vrais appels.
Route::get('/docs', [DocumentationController::class, 'page'])->name('api.docs');
Route::get('/docs/openapi.yaml', [DocumentationController::class, 'specification'])->name('api.docs.specification');

// Le journal des refus de limite passe avant la limite : il voit son 429.
Route::prefix('v1')->middleware(['journal.limite', 'throttle:api'])->group(function () {
    Route::middleware('cle.api:lecture')->group(function () {
        Route::get('/expeditions', [ExpeditionController::class, 'index'])
            ->name('api.expeditions.index');
        Route::get('/expeditions/{numero}', [ExpeditionController::class, 'show'])
            ->name('api.expeditions.show');
    });

    Route::middleware('cle.api:ecriture')->group(function () {
        Route::post('/expeditions', [ExpeditionController::class, 'store'])
            ->name('api.expeditions.store');
    });
});
