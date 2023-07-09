<?php

use App\Dataproviders\Cardlists\Project\PKSancCardList;
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

    Route::get('/deposit', [PKSancController::class, 'showDeposit'])
        ->name('pksanc.deposit.show');

    Route::post('/deposit/stage', [PKSancController::class, 'stageDepositAttempt'])
        ->name('pksanc.deposit.stage.attempt');

    // data providers
    Route::get('/data/overview', [PKSancCardList::class, 'overviewData'])
        ->name('pksanc.overview.cardlist');
});
