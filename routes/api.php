<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InstallmentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserLimitController;
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

// Public subscription plans
Route::get('subscriptions-public', [SubscriptionController::class, 'publicIndex']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('logout', 'logout');
        Route::get('me', 'me');
        Route::post('refresh', 'refresh');
    });

    Route::prefix('limits')->controller(UserLimitController::class)->group(function () {
        Route::get('current', 'current');
        Route::get('can-create/{resourceType}', 'canCreate');
        Route::post('refresh', 'refreshUsage');
        Route::post('increment/{resourceType}', 'increment');
        Route::post('decrement/{resourceType}', 'decrement');
        Route::get('feature/{feature}', 'feature');
    });

    // Routes below require an active subscription
    Route::middleware(\App\Http\Middleware\EnsureActiveSubscription::class)->group(function () {
        // Dashboard
        Route::get('dashboard', [InstallmentController::class, 'dashboard']);

        // Notifications & Emails
        Route::controller(NotificationController::class)->group(function () {
            Route::get('notification-list', 'index');
            Route::get('notification-count', 'count');
            Route::post('notification-mark-read/{id}', 'markAsRead');
            Route::post('notification-mark-all-read', 'markAllAsRead');
            Route::post('notification-generate', 'generate');
            Route::post('notification-send-emails', 'sendReminderEmails');
            Route::delete('notification-delete/{id}', 'destroy');
        });

        // Customer routes
        Route::controller(CustomerController::class)->group(function () {
            Route::get('customer-list', 'index');
            Route::get('customer-for-select', 'forSelect');
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
            Route::get('installment-stats/{id}', 'stats');
            Route::get('installment-all-stats', 'allStats');
            Route::post('installment-item-pay/{item}', 'markItemPaid');
        });
    });

    Route::middleware('owner')->group(function () {
        Route::controller(SubscriptionController::class)->group(function () {
            Route::get('subscriptions-admin', 'index');
            Route::post('subscriptions-create', 'store');
            Route::get('subscriptions-show/{subscription}', 'show');
            Route::put('subscriptions-update/{subscription}', 'update');
            Route::delete('subscriptions-delete/{subscription}', 'destroy');
            Route::post('subscriptions/{subscription}/assign', 'assign');
        });

        Route::controller(UserLimitController::class)->group(function () {
            Route::get('limits', 'index');
            Route::post('limits', 'store');
            Route::get('limits/{userLimit}', 'show');
            Route::put('limits/{userLimit}', 'update');
            Route::delete('limits/{userLimit}', 'destroy');
        });

        Route::controller(UserController::class)->group(function () {
            Route::get('user-list', 'index');
            Route::post('user-create', 'store');
            Route::get('user-show/{id}', 'show');
            Route::put('user-update/{id}', 'update');
            Route::delete('user-delete/{id}', 'destroy');
        });
    });
});
