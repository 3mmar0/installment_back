<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Client\ClientAuthController;
use App\Http\Controllers\Api\Client\ClientNotificationController;
use App\Http\Controllers\Api\Client\ClientPaymentRequestController;
use App\Http\Controllers\Api\Client\ClientPortalController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ExportReportController;
use App\Http\Controllers\Api\InstallmentController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentRequestController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SystemSettingsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserLimitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('login', 'login')->middleware('throttle:10,1');
    Route::post('register', 'register')->middleware('throttle:10,1');
    Route::post('forgot-password', 'forgotPassword')->middleware('throttle:5,1');
    Route::post('reset-password', 'resetPassword')->middleware('throttle:10,1');
});

// Public client auth
Route::prefix('client/auth')->controller(ClientAuthController::class)->group(function () {
    Route::post('register', 'register')->middleware('throttle:5,1');
    Route::post('login', 'login')->middleware('throttle:10,1');
    Route::post('verify-otp', 'verifyOtp')->middleware('throttle:5,1');
    Route::post('resend-otp', 'resendOtp')->middleware('throttle:3,1');
});

// Public subscription plans
Route::get('subscriptions-public', [SubscriptionController::class, 'publicIndex']);

// Public trial settings & legal pages
Route::get('settings/trial', [SettingsController::class, 'trialPublic']);
Route::get('legal/privacy', [LegalController::class, 'privacy']);
Route::get('legal/terms', [LegalController::class, 'terms']);

// Protected client routes
Route::middleware(['auth:sanctum', 'client', 'track.activity'])->prefix('client')->group(function () {
    Route::prefix('auth')->controller(ClientAuthController::class)->group(function () {
        Route::post('logout', 'logout');
        Route::get('me', 'me');
        Route::post('refresh', 'refresh');
    });

    Route::controller(ClientPortalController::class)->group(function () {
        Route::get('dashboard', 'dashboard');
        Route::get('installment-list', 'installmentList');
        Route::get('installment-show/{id}', 'installmentShow');
    });

    Route::controller(ClientPaymentRequestController::class)->group(function () {
        Route::post('payment-request-create', 'store');
        Route::get('payment-request-list', 'index');
        Route::get('payment-request-attachment/{id}', 'attachment');
    });

    Route::controller(ClientNotificationController::class)->group(function () {
        Route::get('notification-list', 'index');
        Route::get('notification-count', 'count');
        Route::post('notification-mark-read/{id}', 'markAsRead');
    });
});

// Protected vendor routes
Route::middleware(['auth:sanctum', 'vendor', 'track.activity'])->group(function () {
    // Auth routes
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('logout', 'logout');
        Route::get('me', 'me');
        Route::put('profile', 'updateProfile');
        Route::post('refresh', 'refresh');
        Route::delete('account', 'deleteAccount');
    });

    Route::prefix('limits')->controller(UserLimitController::class)->group(function () {
        Route::get('current', 'current');
        Route::get('can-create/{resourceType}', 'canCreate');
        Route::post('refresh', 'refreshUsage');
        Route::get('feature/{feature}', 'feature');
    });

    // User subscription change (upgrade/downgrade) and cancel current plan
    Route::post('subscriptions/cancel', [SubscriptionController::class, 'cancelCurrent']);
    Route::post('subscriptions/{subscription}/change', [SubscriptionController::class, 'changeSubscription']);

    // Support / complaints (no active subscription required)
    Route::controller(ComplaintController::class)->group(function () {
        Route::get('complaint-list', 'index');
        Route::post('complaint-create', 'store');
        Route::get('complaint-show/{id}', 'show');
        Route::post('complaint-reply/{id}', 'reply');
    });

    Route::middleware('platform_admin')->controller(SystemSettingsController::class)->group(function () {
        Route::get('admin/system/queue-status', 'queueStatus');
        Route::post('admin/system/queue-start', 'startQueue');
        Route::post('admin/system/queue-stop', 'stopQueue');
        Route::post('admin/system/queue-run', 'runQueue');
        Route::post('admin/system/cache-clear', 'clearCache');
        Route::get('admin/system/users', 'users');
        Route::post('admin/system/broadcast-notification', 'broadcastNotification');
        Route::post('admin/system/sync-free-plan', 'syncFreePlan');
    });

    // Routes below require an active subscription
    Route::middleware(\App\Http\Middleware\EnsureActiveSubscription::class)->group(function () {
        // Dashboard
        Route::get('dashboard', [InstallmentController::class, 'dashboard']);

        // Exports (binary download — POST JSON body e.g. { "scope": "dashboard" })
        Route::post('export/pdf', [ExportReportController::class, 'pdf']);
        Route::post('export/excel', [ExportReportController::class, 'excel']);
        Route::post('export/csv', [ExportReportController::class, 'csv']);

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
            Route::post('customer-send-reminders/{id}', 'sendReminders');
        });

        // Installment routes
        Route::controller(InstallmentController::class)->group(function () {
            Route::get('installment-list', 'index');
            Route::post('installment-create', 'store');
            Route::get('installment-overdue', 'overdue');
            Route::get('installment-due-soon', 'dueSoon');
            Route::get('installment-show/{id}', 'show');
            Route::put('installment-update/{id}', 'update');
            Route::delete('installment-delete/{id}', 'destroy');
            Route::get('installment-stats/{id}', 'stats');
            Route::get('installment-all-stats', 'allStats');
            Route::post('installment-item-pay/{item}', 'markItemPaid');
            Route::post('installment-remind/{id}', 'sendReminders');
        });

        // Payment request review (vendor)
        Route::controller(PaymentRequestController::class)->group(function () {
            Route::get('payment-request-list', 'index');
            Route::get('payment-request-count', 'pendingCount');
            Route::post('payment-request-approve/{id}', 'approve');
            Route::post('payment-request-reject/{id}', 'reject');
            Route::get('payment-request-attachment/{id}', 'attachment');
        });
    });

    Route::middleware('owner')->group(function () {
        Route::put('settings/trial', [SettingsController::class, 'updateTrial']);

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
