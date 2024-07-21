<?php

use Illuminate\Support\Facades\Route;

Route::prefix('/config/user')
    ->middleware([
        'auth:sanctum',
        'auth.permission:config.user.view'
    ])
    ->group(function() {
        Route::post('/{user_uuid}/set_role/{permission_id}', [\App\Http\Api\Config\UserConfigApi::class, 'changeRole'])
            ->middleware('auth.permission:config.user.edit,config.user.role')
            ->name('api.config.users.change-role');
    });

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
