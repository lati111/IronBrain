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
    });

    //| nav
    Route::prefix('/nav')->group(function() {
        Route::get('/', [NavController::class, 'overview'])->name("config.nav.overview");
        Route::get('/new', [NavController::class, 'new'])->name("config.nav.new");
        Route::get('/modify/{id}', [NavController::class, 'modify'])->name("config.nav.modify");
        Route::post('/save', [NavController::class, 'save'])->name("config.nav.save");
        Route::post('/delete/{id}', [NavController::class, 'delete'])->name("config.nav.delete");

        //| submenu
        Route::prefix('/{projectId}/submenu')->group(function() {
            Route::get('/new', [NavController::class, 'newSubmenu'])->name("config.submenu.new");
            Route::get('/modify/{id}', [NavController::class, 'modify'])->name("config.submenu.modify");
            Route::post('/save', [NavController::class, 'save'])->name("config.submenu.save");
            Route::post('/delete/{id}', [NavController::class, 'delete'])->name("config.submenu.delete");
        });
    });
});
