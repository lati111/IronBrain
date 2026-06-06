<?php

use Illuminate\Support\Facades\Route;

//| Authentication

Route::prefix('/auth')
    ->group(function() {
        Route::post('/login', [\App\Http\Api\Auth\UserAuthApi::class, 'attemptLogin'])
            ->name('api.auth.login');

        Route::post('/signup', [\App\Http\Api\Auth\UserAuthApi::class, 'createAccount'])
            ->name('api.auth.signup');
    });

//| Config

Route::prefix('/config')
    ->middleware(['auth:sanctum'])
    ->group(function() {

        Route::prefix('/user')
            ->middleware(['auth.permission:config.user.view'])
            ->group(function() {
                Route::post('/{user_uuid}/set_role/{permission_id}', [\App\Http\Api\Config\UserConfigApi::class, 'changeRole'])
                    ->middleware('auth.permission:config.user.edit,config.user.role')
                    ->name('api.config.users.change-role');
            });

        Route::prefix('/role')
            ->middleware(['auth.permission:config.role.view'])
            ->group(function() {

                Route::prefix('/{role_id}/permission')
                    ->middleware(['auth.permission:config.role.permissions'])
                    ->group(function() {
                        Route::post('/{permission_id}/add', [\App\Http\Api\Config\RoleConfigApi::class, 'grantPermission'])
                            ->name('api.config.role.permissions.grant');

                        Route::post('/{permission_id}/remove', [\App\Http\Api\Config\RoleConfigApi::class, 'revokePermission'])
                            ->name('api.config.role.permissions.revoke');
                    });
            });
    });


//| Arsenal

Route::prefix('/arsenal')
    ->middleware('auth:sanctum')
    ->group(function() {
        Route::post('/armory/add', [\App\Http\Api\Modules\Arsenal\ArmoryApi::class, 'addItem'])
            ->name('api.arsenal.armory.add');
        Route::get('/armory/item', [\App\Http\Api\Modules\Arsenal\ArmoryApi::class, 'getItem'])
            ->name('api.arsenal.armory.item.get');
        Route::post('/armory/update', [\App\Http\Api\Modules\Arsenal\ArmoryApi::class, 'updateItem'])
            ->name('api.arsenal.armory.item.update');
        Route::post('/armory/remove', [\App\Http\Api\Modules\Arsenal\ArmoryApi::class, 'removeItem'])
            ->name('api.arsenal.armory.item.remove');
        Route::post('/armory/duplicate', [\App\Http\Api\Modules\Arsenal\ArmoryApi::class, 'duplicateItem'])
            ->name('api.arsenal.armory.item.duplicate');
    });


//| PKSanc

Route::prefix('/pksanc')
    ->middleware('auth:sanctum')
    ->group(function() {
        Route::prefix('/deposit')->group(function() {
            Route::prefix('/staging')->group(function() {
                Route::post('/', [\App\Http\Api\Modules\PKSanc\DepositApi::class, 'stageDepositAttempt'])
                    ->name('pksanc.deposit.stage.attempt');

                Route::prefix('/{staging_uuid}')->group(function() {
                    Route::post('/confirm', [\App\Http\Api\Modules\PKSanc\DepositApi::class, 'confirmDeposit'])
                        ->name('pksanc.deposit.stage.confirm');
                });
            });
        });

        Route::post('/romhacks/add', [\App\Http\Api\Modules\PKSanc\ContributionApi::class, 'addRomhack'])
            ->name('pksanc.games.romhacks.add');

        Route::prefix('/pokedex')->group(function() {
            Route::post('/mark', [\App\Http\Api\Modules\PKSanc\PokedexApi::class, 'setPokedexMarking'])
                ->name('pksanc.pokedex.mark');

            Route::post('/unmark', [\App\Http\Api\Modules\PKSanc\PokedexApi::class, 'removePokedexMarking'])
                ->name('pksanc.pokedex.unmark');
        });
    });
