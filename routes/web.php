<?php

use App\Dataproviders\Cardlists\Modules\PKSanc\PKSancOverviewCardList;
use App\Dataproviders\Cardlists\Modules\PKSanc\PKSancStagingCardList;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Projects\PKSanc\PKSancController;
use App\Http\Controllers\Projects\PKSanc\PKSancDepositController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'show'])->name("home.show");

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
Route::prefix('/pksanc')->group(function() {
    // pages
    Route::get('/', [PKSancController::class, 'showOverview'])
        ->name('pksanc.home.show');

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

    // data providers
    Route::prefix('/data/overview')->group(function() {
        Route::get('/', [PKSancOverviewCardList::class, 'data'])
            ->name('pksanc.overview.cardlist');

        Route::get('/count', [PKSancOverviewCardList::class, 'count'])
            ->name('pksanc.overview.count');

        Route::get('/filters', [PKSancOverviewCardList::class, 'filters'])
            ->name('pksanc.overview.filters');
    });

    Route::prefix('/data/staging/{import_uuid}')->group(function() {
        Route::get('/', [PKSancStagingCardList::class, 'data'])
            ->name('pksanc.staging.cardlist');

        Route::get('/count', [PKSancStagingCardList::class, 'count'])
            ->name('pksanc.staging.count');

        Route::get('/filters', [PKSancStagingCardList::class, 'filters'])
            ->name('pksanc.staging.filters');
    });
});
