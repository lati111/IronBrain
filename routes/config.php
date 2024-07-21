<?php

use App\Http\Controllers\Config\PermissionController;
use App\Http\Controllers\Config\RoleController;
use App\Http\Controllers\Config\UserController;
use App\Http\Dataproviders\Datatables\Auth\PermissionDatatable;
use App\Http\Dataproviders\Datatables\Auth\RoleDatatable;
use App\Http\Dataproviders\Datatables\Auth\UserDatatable;
use App\Http\Dataproviders\SelectorLists\Auth\PermissionSelectorList;
use Illuminate\Support\Facades\Route;

Route::prefix('/config')
    ->middleware('auth:sanctum')
    ->group(function() {
        //| user control
        Route::prefix('/user')
            ->middleware('auth.permission:config.user.view')
            ->group(function() {
            // pages
            Route::get('/', [UserController::class, 'overview'])
                ->name("config.user.overview");

            Route::post('/delete/{uuid}', [UserController::class, 'deactivate'])
                ->middleware('auth.permission:config.user.edit')
                ->name("config.user.delete");

            // data providers
            Route::get('/overview/data', [UserDatatable::class, 'overviewData'])
                ->name("config.user.overview.datatable");

            // ajax calls
            Route::post('/{uuid}/role/set', [UserController::class, 'setRole'])
                ->middleware('auth.permission:config.user.edit')
                ->name("config.user.role.set");
        });

        //| role
        Route::prefix('/role')
            ->middleware('auth.permission:config.role.view')
            ->group(function() {

            // pages
            Route::get('/', [RoleController::class, 'overview'])
                ->name("config.role.overview");

            Route::get('/new', [RoleController::class, 'new'])
                ->middleware('auth.permission:config.role.edit')
                ->name("config.role.new");

            Route::get('/modify/{id}', [RoleController::class, 'modify'])
                ->middleware('auth.permission:config.role.edit')
                ->name("config.role.modify");

            Route::post('/save', [RoleController::class, 'save'])
                ->middleware('auth.permission:config.role.edit')
                ->name("config.role.save");

            Route::post('/delete/{id}', [RoleController::class, 'delete'])
                ->middleware('auth.permission:config.role.edit')
                ->name("config.role.delete");

            // datatables
            Route::get('/overview/data', [RoleDatatable::class, 'overviewData'])
                ->name("config.role.overview.datatable");

            Route::get('/{id}/permissions/data', [PermissionDatatable::class, 'listToggleableData'])
                ->middleware('auth.permission:config.role.edit')
                ->name("config.role.permission.datatable");

            // ajax calls
            Route::post('/{role_id}/permission/{permission_id}/toggle', [RoleController::class, 'togglePermission'])
                ->middleware('auth.permission:config.role.edit')
                ->name("config.role.permission.toggle");
        });

        //| permission
        Route::prefix('/permission')
            ->middleware('auth.permission:config.permission.view')
            ->group(function() {
            // pages
            Route::get('/', [PermissionController::class, 'overview'])
                ->name("config.permission.overview");

            Route::get('/new', [PermissionController::class, 'new'])
                ->middleware('auth.permission:config.permission.edit')
                ->name("config.permission.new");

            Route::get('/modify/{id}', [PermissionController::class, 'modify'])
                ->middleware('auth.permission:config.permission.edit')
                ->name("config.permission.modify");

            Route::post('/save', [PermissionController::class, 'save'])
                ->middleware('auth.permission:config.permission.edit')
                ->name("config.permission.save");

            Route::post('/delete/{id}', [PermissionController::class, 'delete'])
                ->middleware('auth.permission:config.permission.edit')
                ->name("config.permission.delete");

            // data providers
            Route::get('/overview/data', [PermissionDatatable::class, 'overviewData'])
                ->name("config.permission.overview.datatable");

            Route::get('/selector/data', [PermissionSelectorList::class, 'PermissionSelectorList'])
                ->name("config.permission.selector.list");
        });
});
