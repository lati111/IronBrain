<?php

use App\Dataproviders\Datatables\Auth\PermissionDatatable;
use App\Dataproviders\Datatables\Config\ProjectDatatable;
use App\Dataproviders\Datatables\Auth\RoleDatatable;
use App\Dataproviders\Datatables\Auth\UserDatatable;
use App\Dataproviders\Datatables\Config\SubmenuDatatable;
use App\Dataproviders\SelectorLists\Config\PermissionSelectorList;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Config\ProjectController;
use App\Http\Controllers\Config\SubmenuController;
use App\Http\Controllers\Config\PermissionController;
use App\Http\Controllers\Config\RoleController;
use App\Http\Controllers\Config\UserController;

Route::prefix('/config')->group(function() {
    //| project
    Route::prefix('/projects')->group(function() {
        // pages
        Route::get('/', [ProjectController::class, 'overview'])
            ->middleware('permission:config.project.view')
            ->name("config.projects.overview");

        Route::get('/new', [ProjectController::class, 'new'])
            ->middleware('permission:config.project.edit')
            ->name("config.projects.new");

        Route::get('/modify/{id}', [ProjectController::class, 'modify'])
            ->middleware('permission:config.project.edit')
            ->name("config.projects.modify");

        Route::post('/save', [ProjectController::class, 'save'])
            ->middleware('permission:config.project.edit')
            ->name("config.projects.save");

        Route::post('/delete/{id}', [ProjectController::class, 'delete'])
            ->middleware('permission:config.project.edit')
            ->name("config.projects.delete");

        // data providers
        Route::get('/overview/data', [ProjectDatatable::class, 'overviewData'])
            ->middleware('permission:config.project.view')
            ->name("config.project.overview.datatable");


        //| submenu
        Route::prefix('/{project_id}/submenu')->group(function() {
            Route::get('/new', [SubmenuController::class, 'new'])
                ->middleware('permission:config.project.edit')
                ->name("config.projects.submenu.new");

            Route::get('/modify/{id}', [SubmenuController::class, 'modify'])
                ->middleware('permission:config.project.edit')
                ->name("config.projects.submenu.modify");

            Route::post('/save', [SubmenuController::class, 'save'])
                ->middleware('permission:config.project.edit')
                ->name("config.projects.submenu.save");

            Route::post('/delete/{id}', [SubmenuController::class, 'delete'])
                ->middleware('permission:config.project.edit')
                ->name("config.projects.submenu.delete");

            Route::get('/overview/data', [SubmenuDatatable::class, 'overviewData'])
                ->middleware('permission:config.project.edit')
                ->name("config.projects.submenu.overview.datatable");
        });
    });

    //| user control
    Route::prefix('/user')->group(function() {
        // pages
        Route::get('/', [UserController::class, 'overview'])
            ->middleware('permission:config.user.view')
            ->name("config.user.overview");

        Route::post('/delete/{uuid}', [UserController::class, 'deactivate'])
            ->middleware('permission:config.user.edit')
            ->name("config.user.delete");

        // datatables
        Route::get('/overview/data', [UserDatatable::class, 'overviewData'])
            ->middleware('permission:config.user.view')
            ->name("config.user.overview.datatable");

        // ajax calls
        Route::post('/{uuid}/role/set', [UserController::class, 'setRole'])
            ->middleware('permission:config.user.edit')
            ->name("config.user.role.set");
    });

    //| role
    Route::prefix('/role')->group(function() {
        // pages
        Route::get('/', [RoleController::class, 'overview'])
            ->middleware('permission:config.role.view')
            ->name("config.role.overview");

        Route::get('/new', [RoleController::class, 'new'])
            ->middleware('permission:config.role.edit')
            ->name("config.role.new");

        Route::get('/modify/{id}', [RoleController::class, 'modify'])
            ->middleware('permission:config.role.edit')
            ->name("config.role.modify");

        Route::post('/save', [RoleController::class, 'save'])
            ->middleware('permission:config.role.edit')
            ->name("config.role.save");

        Route::post('/delete/{id}', [RoleController::class, 'delete'])
            ->middleware('permission:config.role.edit')
            ->name("config.role.delete");

        // datatables
        Route::get('/overview/data', [RoleDatatable::class, 'overviewData'])
            ->middleware('permission:config.role.view')
            ->name("config.role.overview.datatable");

        Route::get('/{id}/permissions/data', [PermissionDatatable::class, 'listToggleableData'])
            ->middleware('permission:config.role.edit')
            ->name("config.role.permission.datatable");

        // ajax calls
        Route::post('/{role_id}/permission/{permission_id}/toggle', [RoleController::class, 'togglePermission'])
            ->middleware('permission:config.role.edit')
            ->name("config.role.permission.toggle");
    });

    //| permission
    Route::prefix('/permission')->group(function() {
        // pages
        Route::get('/', [PermissionController::class, 'overview'])
            ->middleware('permission:config.permission.view')
            ->name("config.permission.overview");

        Route::get('/new', [PermissionController::class, 'new'])
            ->middleware('permission:config.permission.edit')
            ->name("config.permission.new");

        Route::get('/modify/{id}', [PermissionController::class, 'modify'])
            ->middleware('permission:config.permission.edit')
            ->name("config.permission.modify");

        Route::post('/save', [PermissionController::class, 'save'])
            ->middleware('permission:config.permission.edit')
            ->name("config.permission.save");

        Route::post('/delete/{id}', [PermissionController::class, 'delete'])
            ->middleware('permission:config.permission.edit')
            ->name("config.permission.delete");

        // data providers
        Route::get('/overview/data', [PermissionDatatable::class, 'overviewData'])
            ->middleware('permission:config.permission.view')
            ->name("config.permission.overview.datatable");

        Route::get('/selector/data', [PermissionSelectorList::class, 'PermissionSelectorList'])
            ->middleware('permission:config.role.view')
            ->name("config.permission.selector.list");
    });
});
