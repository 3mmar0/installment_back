<?php

use App\Http\Controllers\DocumentationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
| Note: This application is API-only. All functional routes are in api.php
|
*/

Route::get('/', function () {
    return redirect('/documentation');
});

Route::get('/documentation', [DocumentationController::class, 'index'])->name('documentation');
Route::get('/documentation/api', [DocumentationController::class, 'api'])->name('documentation.api');
