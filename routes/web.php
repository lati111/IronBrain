<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Config\ProjectController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [HomeController::class, 'show'])->name("home.show");

Route::prefix('/config')->group(function() {
    Route::get('/projects', [ProjectController::class, 'overview'])->name("config.projects.overview");
    Route::get('/projects/new', [ProjectController::class, 'new'])->name("config.projects.new");
    Route::post('/projects/save', [ProjectController::class, 'save'])->name("config.projects.save");
});
