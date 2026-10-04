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
        Route::post('/{restaurant_uuid}/edit', [\App\Http\Api\Modules\DnD\Restaurant\RestaurantApi::class, 'editRestaurant'])
            ->name('api.dnd.restaurant.edit');

        Route::prefix('/{restaurant_uuid}/service')->group(function() {
            Route::get('/', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'getService'])
                ->name('api.dnd.restaurant.service.get');
            Route::post('/time', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'setTime'])
                ->name('api.dnd.restaurant.service.time');
            Route::get('/summary', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'getSummary'])
                ->name('api.dnd.restaurant.service.summary');
            Route::post('/end', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'endService'])
                ->name('api.dnd.restaurant.service.end');

            Route::post('/meal/plan', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'planMeal'])
                ->name('api.dnd.restaurant.service.meal.plan');
            Route::post('/meal/{meal_uuid}/prepare', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'prepareMeal'])
                ->name('api.dnd.restaurant.service.meal.prepare');
            Route::post('/meal/{meal_uuid}/cook', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'cookMeal'])
                ->name('api.dnd.restaurant.service.meal.cook');
            Route::post('/meal/{meal_uuid}/skip', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'skipMeal'])
                ->name('api.dnd.restaurant.service.meal.skip');
            Route::post('/meal/{meal_uuid}/serve', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'serveMeal'])
                ->name('api.dnd.restaurant.service.meal.serve');
            Route::post('/meal/{meal_uuid}/cancel', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'cancelMeal'])
                ->name('api.dnd.restaurant.service.meal.cancel');
            Route::post('/meal/{meal_uuid}/supply', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'supplyMeal'])
                ->name('api.dnd.restaurant.service.meal.supply');

            Route::post('/meal/{meal_uuid}/quality', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'setMealQuality'])
                ->name('api.dnd.restaurant.service.meal.quality');
            Route::post('/meal/{meal_uuid}/plate', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'plateMeal'])
                ->name('api.dnd.restaurant.service.meal.plate');
            Route::post('/plate/{plate_uuid}/deliver', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'deliverPlate'])
                ->name('api.dnd.restaurant.service.plate.deliver');
            Route::post('/plate/{plate_uuid}/scrap', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'scrapPlate'])
                ->name('api.dnd.restaurant.service.plate.scrap');

            Route::post('/order', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'placeOrder'])
                ->name('api.dnd.restaurant.service.order.place');
            Route::post('/order/{order_uuid}/consume', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'consumeOrder'])
                ->name('api.dnd.restaurant.service.order.consume');
            Route::post('/order/{order_uuid}/edit', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'editOrder'])
                ->name('api.dnd.restaurant.service.order.edit');
            Route::post('/order/{order_uuid}/adjust', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'adjustOrder'])
                ->name('api.dnd.restaurant.service.order.adjust');
            Route::post('/order/{order_uuid}/item/{item_uuid}/consume', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'consumeOrderItem'])
                ->name('api.dnd.restaurant.service.order.item.consume');
            Route::post('/order/{order_uuid}/finish', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'finishOrder'])
                ->name('api.dnd.restaurant.service.order.finish');
            Route::post('/order/{order_uuid}/cancel', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'cancelOrder'])
                ->name('api.dnd.restaurant.service.order.cancel');

            Route::post('/sale/{recipe_uuid}', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'setSale'])
                ->name('api.dnd.restaurant.service.sale.set');
            Route::post('/sale/{recipe_uuid}/remove', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'removeSale'])
                ->name('api.dnd.restaurant.service.sale.remove');

            Route::post('/ingredient/{ingredient_uuid}/add', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'addIngredient'])
                ->name('api.dnd.restaurant.service.ingredient.add');

            Route::post('/work/{work_uuid}/prioritize', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'prioritizeWork'])
                ->name('api.dnd.restaurant.service.work.prioritize');
            Route::post('/conversion/{conversion_uuid}/queue', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'queueConversion'])
                ->name('api.dnd.restaurant.service.conversion.queue');
            Route::post('/work/{work_uuid}/cancel', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'cancelConversion'])
                ->name('api.dnd.restaurant.service.conversion.cancel');
            Route::post('/table/{table_uuid}/waiter', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'assignWaiter'])
                ->name('api.dnd.restaurant.service.table.waiter');
            Route::post('/employee/{employee_uuid}/assign', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'assignEmployee'])
                ->name('api.dnd.restaurant.service.employee.assign');

            Route::post('/equipment/clean-all', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'cleanAllEquipment'])
                ->name('api.dnd.restaurant.service.equipment.clean-all');
            Route::post('/equipment/{equipment_uuid}/clean', [\App\Http\Api\Modules\DnD\Restaurant\ServiceApi::class, 'cleanEquipment'])
                ->name('api.dnd.restaurant.service.equipment.clean');
        });

        Route::prefix('/{restaurant_uuid}/floor')->group(function() {
            Route::get('/', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'getFloor'])
                ->name('api.dnd.restaurant.floor.get');

            Route::post('/room/create', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'createRoom'])
                ->name('api.dnd.restaurant.floor.room.create');
            Route::post('/room/reorder', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'reorderRooms'])
                ->name('api.dnd.restaurant.floor.room.reorder');
            Route::post('/room/{room_uuid}/delete', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'deleteRoom'])
                ->name('api.dnd.restaurant.floor.room.delete');

            Route::post('/table/create', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'createTable'])
                ->name('api.dnd.restaurant.floor.table.create');
            Route::post('/table/{table_uuid}/edit', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'editTable'])
                ->name('api.dnd.restaurant.floor.table.edit');
            Route::post('/table/{table_uuid}/move', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'moveTable'])
                ->name('api.dnd.restaurant.floor.table.move');
            Route::post('/table/{table_uuid}/delete', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'deleteTable'])
                ->name('api.dnd.restaurant.floor.table.delete');

            Route::post('/employee/create', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'createEmployee'])
                ->name('api.dnd.restaurant.floor.employee.create');
            Route::post('/employee/{employee_uuid}/edit', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'editEmployee'])
                ->name('api.dnd.restaurant.floor.employee.edit');
            Route::post('/employee/{employee_uuid}/delete', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'deleteEmployee'])
                ->name('api.dnd.restaurant.floor.employee.delete');

            Route::post('/customer/create', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'createCustomer'])
                ->name('api.dnd.restaurant.floor.customer.create');
            Route::post('/customer/{customer_uuid}/edit', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'editCustomer'])
                ->name('api.dnd.restaurant.floor.customer.edit');
            Route::post('/customer/{customer_uuid}/seat', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'seatCustomer'])
                ->name('api.dnd.restaurant.floor.customer.seat');
            Route::post('/customer/{customer_uuid}/delete', [\App\Http\Api\Modules\DnD\Restaurant\FloorApi::class, 'deleteCustomer'])
                ->name('api.dnd.restaurant.floor.customer.delete');
        });

        Route::prefix('/{restaurant_uuid}/inventory')->group(function() {
            Route::get('/', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'getInventory'])
                ->name('api.dnd.restaurant.inventory.get');
            Route::post('/transfer', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'transferItem'])
                ->name('api.dnd.restaurant.inventory.transfer');
            Route::post('/advance-day', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'advanceDay'])
                ->name('api.dnd.restaurant.inventory.advance-day');

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
            Route::post('/ingredient/{ingredient_uuid}/delete', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'deleteIngredient'])
                ->name('api.dnd.restaurant.inventory.ingredient.delete');

            Route::post('/equipment/create', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'createEquipment'])
                ->name('api.dnd.restaurant.inventory.equipment.create');
            Route::post('/equipment/{equipment_uuid}/edit', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'editEquipment'])
                ->name('api.dnd.restaurant.inventory.equipment.edit');
            Route::post('/equipment/{equipment_uuid}/delete', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'deleteEquipment'])
                ->name('api.dnd.restaurant.inventory.equipment.delete');
            Route::post('/equipment/{equipment_uuid}/owned', [\App\Http\Api\Modules\DnD\Restaurant\InventoryApi::class, 'adjustOwnedEquipment'])
                ->name('api.dnd.restaurant.inventory.equipment.owned');

            Route::post('/recipe/create', [\App\Http\Api\Modules\DnD\Restaurant\RecipeApi::class, 'createRecipe'])
                ->name('api.dnd.restaurant.inventory.recipe.create');
            Route::post('/recipe/{recipe_uuid}/edit', [\App\Http\Api\Modules\DnD\Restaurant\RecipeApi::class, 'editRecipe'])
                ->name('api.dnd.restaurant.inventory.recipe.edit');
            Route::post('/recipe/{recipe_uuid}/delete', [\App\Http\Api\Modules\DnD\Restaurant\RecipeApi::class, 'deleteRecipe'])
                ->name('api.dnd.restaurant.inventory.recipe.delete');

            Route::post('/conversion/create', [\App\Http\Api\Modules\DnD\Restaurant\ConversionApi::class, 'createConversion'])
                ->name('api.dnd.restaurant.inventory.conversion.create');
            Route::post('/conversion/{conversion_uuid}/edit', [\App\Http\Api\Modules\DnD\Restaurant\ConversionApi::class, 'editConversion'])
                ->name('api.dnd.restaurant.inventory.conversion.edit');
            Route::post('/conversion/{conversion_uuid}/delete', [\App\Http\Api\Modules\DnD\Restaurant\ConversionApi::class, 'deleteConversion'])
                ->name('api.dnd.restaurant.inventory.conversion.delete');
            Route::post('/conversion/{conversion_uuid}/convert', [\App\Http\Api\Modules\DnD\Restaurant\ConversionApi::class, 'convert'])
                ->name('api.dnd.restaurant.inventory.conversion.convert');
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
