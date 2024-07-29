<?php

use App\Http\Controllers\Config\RoleController;
use App\Http\Controllers\Config\UserController;
use App\Http\Dataproviders\Datatables\Config\PermissionDatatable;
use App\Http\Dataproviders\Datatables\Config\RoleDatatable;
use App\Http\Dataproviders\Datatables\Config\UserOverviewDatatable;
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
            Route::get('/overview/data', [UserOverviewDatatable::class, 'overviewData'])
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
});
