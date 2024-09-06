<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Modules\PKSanc\PKSancController;
use App\Http\Controllers\Modules\PKSanc\PKSancDepositController;
use App\Http\Controllers\Modules\PKSanc\PKSancPokdexController;
use Illuminate\Support\Facades\Route;

//| home
Route::prefix('/')->group(function() {
    Route::get('/', [HomeController::class, 'show'])->name("home");
});

//| authentication
Route::prefix('/auth')->group(function() {
    Route::get('/login', [AuthController::class, 'login'])
        ->name("auth.login");

    Route::get('/signup', [AuthController::class, 'signup'])
        ->name("auth.signup");

    Route::get('/logout', [AuthController::class, 'logout'])
        ->name("auth.logout");
});

//| config
Route::prefix('/config')
    ->middleware('auth:sanctum')
    ->group(function() {

        //| user
        Route::prefix('/user')
            ->middleware('auth.permission:config.user.view')
            ->group(function() {
                Route::get('/', [\App\Http\Controllers\Config\UserController::class, 'overview'])
                    ->name("config.user.overview");
            });

        //| role
        Route::prefix('/role')
            ->middleware('auth.permission:config.role.view')
            ->group(function() {
                Route::get('/', [\App\Http\Controllers\Config\RoleController::class, 'overview'])
                    ->name("config.role.overview");
            });
    });


//| pksanc
Route::prefix('/pksanc')
    ->middleware('auth:sanctum')
    ->group(function() {
    // pages
    Route::get('/', [PKSancController::class, 'showOverview'])
        ->name('pksanc.home.show');

    Route::get('/pokedex', [PKSancPokdexController::class, 'showPokedex'])
        ->name('pksanc.pokedex.show');

    Route::prefix('/deposit')->group(function() {
        Route::get('/', [PKSancDepositController::class, 'showDeposit'])
            ->name('pksanc.deposit.show');

        Route::prefix('/stage')->group(function() {
            Route::post('/', [PKSancDepositController::class, 'stageDepositAttempt'])
                ->name('pksanc.deposit.stage.attempt');

            Route::get('/{import_uuid}', [PKSancDepositController::class, 'showDepositAttempt'])
                ->name('pksanc.deposit.stage.show');

            Route::get('/{import_uuid}/cancel', [PKSancDepositController::class, 'depositCancel'])
                ->name('pksanc.deposit.stage.cancel');
        });
    });
});

//| compendium
Route::prefix('/compendium')
    ->middleware('auth:sanctum')
    ->group(function() {
        Route::get('/campaigns', [\App\Http\Controllers\Modules\Compendium\CompendiumController::class, 'campaigns'])
            ->name('compendium.campaigns');
    });
