<?php

use App\Http\Controllers\Config\NavController;
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
            Route::get('/overview/datatable', [NavController::class, 'getSubmenuCollection'])->name("config.projects.submenu.overview.datatable");
            Route::get('/new', [NavController::class, 'new'])->name("config.projects.submenu.new");
            Route::get('/modify/{id}', [NavController::class, 'modify'])->name("config.projects.submenu.modify");
            Route::get('/delete/{id}', [NavController::class, 'delete'])->name("config.projects.submenu.delete");
        });
    });


});
