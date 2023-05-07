<?php

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
        ->middleware('auth:sanctum')
        ->name("auth.logout");
});
