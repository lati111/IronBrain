<?php

use App\Datatables\Auth\PermissionDatatable;
use App\Datatables\Auth\RoleDatatable;
use App\Datatables\Auth\UserDatatable;
use App\Datatables\Config\SubmenuDatatable;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Config\ProjectController;
use App\Http\Controllers\Config\SubmenuController;
use App\Http\Controllers\Config\PermissionController;
use App\Http\Controllers\Config\RoleController;
use App\Http\Controllers\Config\UserController;

Route::prefix('/config')->group(function() {
    //| project
    Route::prefix('/projects')->group(function() {
        Route::get('/', [ProjectController::class, 'overview'])->name("config.projects.overview");
        Route::get('/new', [ProjectController::class, 'new'])->name("config.projects.new");
        Route::get('/modify/{id}', [ProjectController::class, 'modify'])->name("config.projects.modify");
        Route::post('/save', [ProjectController::class, 'save'])->name("config.projects.save");
        Route::post('/delete/{id}', [ProjectController::class, 'delete'])->name("config.projects.delete");

        //| submenu
        Route::prefix('/{project_id}/submenu')->group(function() {
            Route::get('/new', [SubmenuController::class, 'new'])->name("config.projects.submenu.new");
            Route::get('/modify/{id}', [SubmenuController::class, 'modify'])->name("config.projects.submenu.modify");
            Route::post('/save', [SubmenuController::class, 'save'])->name("config.projects.submenu.save");
            Route::post('/delete/{id}', [SubmenuController::class, 'delete'])->name("config.projects.submenu.delete");

            Route::get('/overview/data', [SubmenuDatatable::class, 'overviewData'])->name("config.projects.submenu.overview.datatable");
        });
    });

    //| user control
    Route::prefix('/user')->group(function() {
        // pages
        Route::get('/', [UserController::class, 'overview'])->name("config.user.overview");
        Route::get('/new', [UserController::class, 'new'])->name("config.user.new");
        Route::post('/save', [UserController::class, 'save'])->name("config.user.save");
        Route::post('/delete/{uuid}', [UserController::class, 'delete'])->name("config.user.delete");

        // datatables
        Route::get('/overview/data', [UserDatatable::class, 'overviewData'])->name("config.user.overview.datatable");

        // ajax calls
        Route::post('/{uuid}/role/set', [UserController::class, 'setPermission'])->name("config.user.role.set");
    });

    //| role
    Route::prefix('/role')->group(function() {
        // pages
        Route::get('/', [RoleController::class, 'overview'])->name("config.role.overview");
        Route::get('/new', [RoleController::class, 'new'])->name("config.role.new");
        Route::get('/modify/{id}', [RoleController::class, 'modify'])->name("config.role.modify");
        Route::post('/save', [RoleController::class, 'save'])->name("config.role.save");
        Route::post('/delete/{id}', [RoleController::class, 'delete'])->name("config.role.delete");

        // datatables
        Route::get('/overview/data', [RoleDatatable::class, 'overviewData'])->name("config.role.overview.datatable");
        Route::get('/{id}/permissions/data', [PermissionDatatable::class, 'listToggleableData'])->name("config.role.permission.datatable");

        // ajax calls
        Route::post('/{role_id}/permission/{permission_id}/toggle', [RoleController::class, 'togglePermission'])->name("config.role.permission.toggle");
    });

    //| permission
    Route::prefix('/permission')->group(function() {
        // pages
        Route::get('/', [PermissionController::class, 'overview'])->name("config.permission.overview");
        Route::get('/new', [PermissionController::class, 'new'])->name("config.permission.new");
        Route::get('/modify/{id}', [PermissionController::class, 'modify'])->name("config.permission.modify");
        Route::post('/save', [PermissionController::class, 'save'])->name("config.permission.save");
        Route::post('/delete/{id}', [PermissionController::class, 'delete'])->name("config.permission.delete");

        // datatables
        Route::get('/overview/data', [PermissionDatatable::class, 'overviewData'])->name("config.permission.overview.datatable");
    });
});
