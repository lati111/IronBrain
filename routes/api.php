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
                Route::post('/{user_uuid}/set_role/{role_id}', [\App\Http\Api\Config\UserConfigApi::class, 'changeRole'])
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

        Route::post('/loadout/create', [\App\Http\Api\Modules\Arsenal\LoadoutApi::class, 'createLoadout'])
            ->name('api.arsenal.loadout.create');
        Route::post('/loadout/slot/assign', [\App\Http\Api\Modules\Arsenal\LoadoutApi::class, 'assignSlot'])
            ->name('api.arsenal.loadout.slot.assign');
        Route::post('/loadout/slot/clear', [\App\Http\Api\Modules\Arsenal\LoadoutApi::class, 'clearSlot'])
            ->name('api.arsenal.loadout.slot.clear');
        Route::post('/loadout/rename', [\App\Http\Api\Modules\Arsenal\LoadoutApi::class, 'renameLoadout'])
            ->name('api.arsenal.loadout.rename');

        Route::post('/foundry/component/set', [\App\Http\Api\Modules\Arsenal\FoundryApi::class, 'setComponentAmount'])
            ->name('api.arsenal.foundry.component.set');
        Route::post('/foundry/blueprint/craft', [\App\Http\Api\Modules\Arsenal\FoundryApi::class, 'craftBlueprint'])
            ->name('api.arsenal.foundry.blueprint.craft');
    });


//| DnD Restaurant

Route::prefix('/dnd/restaurant')
    ->middleware(['auth:sanctum', 'auth.permission:dnd_restaurant.access'])
    ->group(function() {
        Route::post('/create', [\App\Http\Api\Modules\DnD\Restaurant\RestaurantApi::class, 'createRestaurant'])
            ->name('api.dnd.restaurant.create');

        Route::prefix('/{restaurant_uuid}/inventory')->group(function() {
            Route::get('/', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'getInventory'])
                ->name('api.dnd.restaurant.inventory.get');
            Route::post('/transfer', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'transferItem'])
                ->name('api.dnd.restaurant.inventory.transfer');

            Route::post('/storage/create', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'createStorage'])
                ->name('api.dnd.restaurant.inventory.storage.create');
            Route::post('/storage/reorder', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'reorderStorages'])
                ->name('api.dnd.restaurant.inventory.storage.reorder');
            Route::post('/storage/{storage_uuid}/delete', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'deleteStorage'])
                ->name('api.dnd.restaurant.inventory.storage.delete');

            Route::post('/ingredient/create', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'createIngredient'])
                ->name('api.dnd.restaurant.inventory.ingredient.create');
            Route::post('/ingredient/{ingredient_uuid}/edit', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'editIngredient'])
                ->name('api.dnd.restaurant.inventory.ingredient.edit');

            Route::post('/equipment/create', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'createEquipment'])
                ->name('api.dnd.restaurant.inventory.equipment.create');
            Route::post('/equipment/{equipment_uuid}/edit', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'editEquipment'])
                ->name('api.dnd.restaurant.inventory.equipment.edit');
            Route::post('/equipment/{equipment_uuid}/owned', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'adjustOwnedEquipment'])
                ->name('api.dnd.restaurant.inventory.equipment.owned');
        });
    });


//| PKSanc

Route::prefix('/pksanc')
    ->middleware('auth:sanctum')
    ->group(function() {
        Route::prefix('/deposit')->group(function() {
            Route::prefix('/staging')->group(function() {
                Route::post('/', [\App\Http\Api\Modules\PKSanc\DepositApi::class, 'stageDepositAttempt'])
                    ->name('api.pksanc.deposit.stage.attempt');

                Route::prefix('/{staging_uuid}')->group(function() {
                    Route::post('/confirm', [\App\Http\Api\Modules\PKSanc\DepositApi::class, 'confirmDeposit'])
                        ->name('api.pksanc.deposit.stage.confirm');
                });
            });
        });

        Route::post('/romhacks/add', [\App\Http\Api\Modules\PKSanc\ContributionApi::class, 'addRomhack'])
            ->name('api.pksanc.games.romhacks.add');

        Route::prefix('/pokedex')->group(function() {
            Route::post('/mark', [\App\Http\Api\Modules\PKSanc\PokedexApi::class, 'setPokedexMarking'])
                ->name('api.pksanc.pokedex.mark');

            Route::post('/unmark', [\App\Http\Api\Modules\PKSanc\PokedexApi::class, 'removePokedexMarking'])
                ->name('api.pksanc.pokedex.unmark');
        });
    });
