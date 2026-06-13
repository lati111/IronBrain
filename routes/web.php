<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Config\RoleController;
use App\Http\Controllers\Config\UserController;
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
                Route::get('/', [UserController::class, 'overview'])
                    ->name("config.user.overview");
                Route::post('/{uuid}/deactivate', [UserController::class, 'deactivate'])
                    ->name("config.user.delete");
                Route::post('/{uuid}/role/set', [UserController::class, 'setRole'])
                    ->name("config.user.role.set");
            });

        //| role
        Route::prefix('/role')
            ->middleware('auth.permission:config.role.view')
            ->group(function() {
                Route::get('/', [RoleController::class, 'overview'])
                    ->name("config.role.overview");
                Route::get('/new', [RoleController::class, 'new'])
                    ->name("config.role.new");
                Route::get('/{id}/modify', [RoleController::class, 'modify'])
                    ->name("config.role.modify");
                Route::post('/save', [RoleController::class, 'save'])
                    ->name("config.role.save");
                Route::post('/{id}/delete', [RoleController::class, 'delete'])
                    ->name("config.role.delete");
                Route::post('/{role_id}/permission/{permission_id}/toggle', [RoleController::class, 'togglePermission'])
                    ->name("config.role.permission.toggle");
            });
    });


//| pksanc
Route::prefix('/pksanc')
    ->middleware('auth:sanctum')
    ->group(function() {
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

//| arsenal
Route::prefix('/arsenal')
    ->middleware('auth:sanctum')
    ->group(function() {
        Route::get('/', [\App\Http\Controllers\Modules\Arsenal\ArsenalController::class, 'showOverview'])
            ->name('arsenal.home.show');

        Route::get('/armory', [\App\Http\Controllers\Modules\Arsenal\ArsenalController::class, 'showArmory'])
            ->name('arsenal.armory.show');

        Route::get('/foundry', [\App\Http\Controllers\Modules\Arsenal\ArsenalController::class, 'showFoundry'])
            ->name('arsenal.foundry.show');
    });

