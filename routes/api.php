<?php

use App\Http\Controllers\Api\ExpeditionController;
use Illuminate\Support\Facades\Route;

/*
 * A12 : l'API REST des partenaires.
 *
 * Elle est versionnee des le premier jour. Un client qui a integre
 * /v1 doit pouvoir continuer a l'appeler le jour ou le format change :
 * la version suivante vivra a cote, pas a la place.
 *
 * Aucune route n'est publique ici. La permission exigee est declaree
 * sur chacune : le middleware refuse une cle de lecture qui tenterait
 * un depot d'ordre, meme si elle connait l'adresse.
 *
 * La limite de debit protege la base d'une boucle mal ecrite chez un
 * partenaire, ce qui arrive plus souvent qu'une attaque.
 */
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
