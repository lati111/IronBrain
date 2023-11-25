<?php

use App\Dataproviders\Cardlists\Project\PKSanc\PKSancOverviewCardList;
use App\Dataproviders\Cardlists\Project\PKSanc\PKSancStagingCardList;
use App\Http\Controllers\Projects\PKSanc\PKSancController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\HomeController;

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
        Route::get('/', [PKSancController::class, 'showDeposit'])
        ->name('pksanc.deposit.show');

        Route::prefix('/stage')->group(function() {
            Route::post('/', [PKSancController::class, 'stageDepositAttempt'])
                ->name('pksanc.deposit.stage.attempt');

            Route::get('/{importUuid}', [PKSancController::class, 'showDepositAttempt'])
                ->name('pksanc.deposit.stage.show');

            Route::get('/{importUuid}/confirm', [PKSancController::class, 'depositConfirm'])
                ->name('pksanc.deposit.stage.confirm');

            Route::get('/{importUuid}/cancel', [PKSancController::class, 'depositCancel'])
                ->name('pksanc.deposit.stage.cancel');
        });
    });

    // data providers
    Route::get('/data/overview', [PKSancOverviewCardList::class, 'data'])
        ->name('pksanc.overview.cardlist');

    Route::get('/data/overview/count', [PKSancOverviewCardList::class, 'count'])
        ->name('pksanc.overview.count');

    Route::get('/data/overview/filters', [PKSancOverviewCardList::class, 'filters'])
        ->name('pksanc.overview.filters');

    Route::get('/data/staging/{importUuid}', [PKSancStagingCardList::class, 'data'])
        ->name('pksanc.staging.cardlist');
});
