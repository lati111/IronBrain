<?php

use App\Datatables\Auth\PermissionDatatable;
use App\Datatables\Config\SubmenuDatatable;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Config\ProjectController;
use App\Http\Controllers\Config\SubmenuController;
use App\Http\Controllers\Config\PermissionController;

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

    //| permission
    Route::prefix('/permission')->group(function() {
        Route::get('/', [PermissionController::class, 'overview'])->name("config.permission.overview");
        Route::get('/new', [PermissionController::class, 'new'])->name("config.permission.new");
        Route::get('/modify/{id}', [PermissionController::class, 'modify'])->name("config.permission.modify");
        Route::post('/save', [PermissionController::class, 'save'])->name("config.permission.save");
        Route::post('/delete/{id}', [PermissionController::class, 'delete'])->name("config.permission.delete");

        Route::get('/overview/data', [PermissionDatatable::class, 'overviewData'])->name("config.permission.overview.datatable");
    });
});
