<?php

use App\Http\Controllers\Api\ExpeditionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:120,1')->group(function () {
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
