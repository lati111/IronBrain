<?php

use App\Http\Controllers\Config\SubmenuController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Config\ProjectController;

Route::get('/', [HomeController::class, 'show'])->name("home.show");

//| config
Route::prefix('/config')->group(function() {
    Route::get('/', [HomeController::class, 'show'])->name("config.overview");

    //| project
    Route::prefix('/projects')->group(function() {
        Route::get('/', [ProjectController::class, 'overview'])->name("config.projects.overview");
        Route::get('/new', [ProjectController::class, 'new'])->name("config.projects.new");
        Route::get('/modify/{id}', [ProjectController::class, 'modify'])->name("config.projects.modify");
        Route::post('/save', [ProjectController::class, 'save'])->name("config.projects.save");
        Route::post('/delete/{id}', [ProjectController::class, 'delete'])->name("config.projects.delete");

        //| submenu
        Route::prefix('/{projectId}/submenu')->group(function() {
            Route::get('/new', [SubmenuController::class, 'new'])->name("config.projects.submenu.new");
            Route::get('/modify/{id}', [SubmenuController::class, 'modify'])->name("config.projects.submenu.modify");
            Route::post('/save', [SubmenuController::class, 'save'])->name("config.projects.submenu.save");
            Route::get('/delete/{id}', [SubmenuController::class, 'delete'])->name("config.projects.submenu.delete");

            Route::get('/overview/datatable', [SubmenuController::class, 'overviewDataTable'])->name("config.projects.submenu.overview.datatable");
        });
    });


});
