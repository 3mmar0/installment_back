<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InstallmentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('register', 'register');
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('logout', 'logout');
        Route::get('me', 'me');
        Route::post('refresh', 'refresh');
    });

    // Dashboard
    Route::get('dashboard', [InstallmentController::class, 'dashboard']);

    // Notifications
    Route::controller(\App\Http\Controllers\Api\NotificationController::class)->group(function () {
        Route::get('notification-list', 'index');
        Route::get('notification-count', 'count');
        Route::post('notification-mark-read/{id}', 'markAsRead');
        Route::post('notification-mark-all-read', 'markAllAsRead');
        Route::post('notification-generate', 'generate');
        Route::delete('notification-delete/{id}', 'destroy');
    });

    // Customer routes
    Route::controller(CustomerController::class)->group(function () {
        Route::get('customer-list', 'index');
        Route::post('customer-create', 'store');
        Route::get('customer-show/{id}', 'show');
        Route::put('customer-update/{id}', 'update');
        Route::delete('customer-delete/{id}', 'destroy');
        Route::get('customer-stats/{id}', 'stats');
    });

    // Installment routes
    Route::controller(InstallmentController::class)->group(function () {
        Route::get('installment-list', 'index');
        Route::post('installment-create', 'store');
        Route::get('installment-overdue', 'overdue');
        Route::get('installment-due-soon', 'dueSoon');
        Route::get('installment-show/{id}', 'show');
        Route::post('installment-item-pay/{item}', 'markItemPaid');
    });

    // User routes (Owner only)
    Route::middleware('owner')->controller(UserController::class)->group(function () {
        Route::get('user-list', 'index');
        Route::post('user-create', 'store');
        Route::get('user-show/{id}', 'show');
        Route::put('user-update/{id}', 'update');
        Route::delete('user-delete/{id}', 'destroy');
    });
});
