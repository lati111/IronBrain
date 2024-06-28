<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Modules\PKSanc\PKSancContributionController;
use App\Http\Controllers\Modules\PKSanc\PKSancController;
use App\Http\Controllers\Modules\PKSanc\PKSancDepositController;
use App\Http\Dataproviders\Cardlists\Config\ProjectOverviewCardlist;
use App\Http\Dataproviders\Cardlists\Modules\PKSanc\PKSancOverviewCardList;
use App\Http\Dataproviders\Cardlists\Modules\PKSanc\PKSancPokedexCardList;
use App\Http\Dataproviders\Cardlists\Modules\PKSanc\PKSancStagingCardList;
use App\Http\Dataproviders\SelectorLists\Modules\PKSanc\FilterSelects\OwnedPokemonSpecies;
use App\Http\Dataproviders\SelectorLists\Modules\PKSanc\GameDataSelect;
use Illuminate\Support\Facades\Route;

//| home
Route::prefix('/')->group(function() {
    Route::get('/', [HomeController::class, 'show'])->name("home.show");

    // data providers
    Route::prefix('/home/data/overview')->group(function() {
        Route::get('/', [ProjectOverviewCardlist::class, 'data'])
            ->name('home.overview.cardlist');

        Route::get('/pages', [ProjectOverviewCardlist::class, 'count'])
            ->name('home.overview.pages');
    });
});

//| authentication
Route::prefix('/auth')->group(function() {
    Route::get('/signup', [AuthController::class, 'showSignup'])
        ->name("auth.signup.show");

    Route::post('/signup/save', [AuthController::class, 'saveSignup'])
        ->name("auth.signup.save");

    Route::get('/login', [AuthController::class, 'showLogin'])
        ->name("auth.login.show");

    Route::post('/login/save', [AuthController::class, 'attemptLogin'])
        ->name("auth.login.attempt");

    Route::get('/logout', [AuthController::class, 'logout'])
        ->name("auth.logout");
});

//| pksanc
Route::prefix('/pksanc')
    ->middleware('auth:sanctum')
    ->group(function() {
    // pages
    Route::get('/', [PKSancController::class, 'showOverview'])
        ->name('pksanc.home.show');

    Route::get('/pokedex', [PKSancController::class, 'showPokedex'])
        ->name('pksanc.pokedex.show');

    Route::prefix('/deposit')->group(function() {
        Route::get('/', [PKSancDepositController::class, 'showDeposit'])
            ->name('pksanc.deposit.show');

        Route::prefix('/stage')->group(function() {
            Route::post('/', [PKSancDepositController::class, 'stageDepositAttempt'])
                ->name('pksanc.deposit.stage.attempt');

            Route::get('/{importUuid}', [PKSancDepositController::class, 'showDepositAttempt'])
                ->name('pksanc.deposit.stage.show');

            Route::get('/{importUuid}/confirm', [PKSancDepositController::class, 'depositConfirm'])
                ->name('pksanc.deposit.stage.confirm');

            Route::get('/{importUuid}/cancel', [PKSancDepositController::class, 'depositCancel'])
                ->name('pksanc.deposit.stage.cancel');
        });
    });
});
