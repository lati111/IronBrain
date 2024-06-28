<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('/pksanc')
    ->middleware('auth:sanctum')
    ->group(function() {
        Route::prefix('/deposit')->group(function() {
            Route::prefix('/stage')->group(function() {
                Route::post('/', [\App\Http\Api\Modules\PKSanc\DepositApi::class, 'stageDepositAttempt'])
                    ->name('pksanc.deposit.stage.attempt');
            });
        });

        Route::post('/romhacks/add', [\App\Http\Api\Modules\PKSanc\ContributionApi::class, 'addRomhack'])
            ->name('pksanc.games.romhacks.add');

        Route::prefix('/pokedex')
            ->middleware('auth:sanctum')
            ->group(function() {
                Route::post('/mark', [\App\Http\Api\Modules\PKSanc\PokedexApi::class, 'setPokedexMarking'])
                    ->name('pksanc.pokedex.mark');

                Route::post('/unmark', [\App\Http\Api\Modules\PKSanc\PokedexApi::class, 'removePokedexMarking'])
                    ->name('pksanc.pokedex.unmark');
            });
    });
