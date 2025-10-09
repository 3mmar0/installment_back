<?php

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
    return response()->json([
        'success' => true,
        'message' => 'Installment Manager API',
        'version' => '1.0.0',
        'documentation' => url('/api/documentation'),
    ]);
});
