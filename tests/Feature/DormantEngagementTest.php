<?php

use App\Models\ClientAccount;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Notification;
use App\Services\EmailNotificationService;
use App\Services\NotificationService;
use App\Support\Engagement;

it('treats a merchant unused for more than 5 months as inactive for comms', function () {
    $user = merchantWithPlan();
    $user->forceFill([
        'created_at' => now()->subMonths(8),
        'last_active_at' => now()->subMonths(Engagement::INACTIVE_AFTER_MONTHS)->subDay(),
    ])->save();

    expect($user->fresh()->receivesOperationalComms())->toBeFalse();
});

it('keeps a recently used merchant eligible for comms', function () {
    $user = merchantWithPlan();
    $user->forceFill(['last_active_at' => now()->subWeek()])->save();

    expect($user->fresh()->receivesOperationalComms())->toBeTrue();
});

it('uses created_at when the merchant never opened the app', function () {
    $fresh = merchantWithPlan();
    $fresh->forceFill([
        'created_at' => now()->subDays(3),
        'last_active_at' => null,
    ])->save();

    $stale = merchantWithPlan();
    $stale->forceFill([
        'created_at' => now()->subMonths(8),
        'last_active_at' => null,
    ])->save();

    expect($fresh->fresh()->receivesOperationalComms())->toBeTrue()
        ->and($stale->fresh()->receivesOperationalComms())->toBeFalse();
});

it('does not create notifications for an inactive merchant', function () {
    $user = merchantWithPlan();
    $user->forceFill([
        'created_at' => now()->subMonths(8),
        'last_active_at' => now()->subMonths(6),
    ])->save();

    $created = app(NotificationService::class)->create(
        $user,
        'payment_due',
        'عنوان',
        'رسالة',
        [],
        enforceLimits: false
    );

    expect($created)->toBeNull()
        ->and(Notification::where('user_id', $user->id)->count())->toBe(0);
});

it('does not notify a client when their vendor is inactive', function () {
    $vendor = merchantWithPlan();
    $vendor->forceFill([
        'created_at' => now()->subMonths(8),
        'last_active_at' => now()->subMonths(6),
    ])->save();

    $client = ClientAccount::create([
        'name' => 'عميل البوابة',
        'email' => 'dormant-client@example.com',
        'phone' => '01000000999',
        'phone_normalized' => '1000000999',
        'password' => 'Password123!',
        'email_verified_at' => now(),
        'last_active_at' => now(),
    ]);

    $customer = Customer::factory()->forMerchant($vendor)->create([
        'client_account_id' => $client->id,
    ]);
    $installment = Installment::factory()->forCustomer($customer)->create();

    $created = app(NotificationService::class)->createForClient(
        $client,
        'payment_due',
        'عنوان',
        'رسالة',
        ['installment_id' => $installment->id]
    );

    expect($created)->toBeNull()
        ->and(Notification::where('client_account_id', $client->id)->count())->toBe(0);
});

it('does not queue reminder emails for an inactive merchant', function () {
    $user = merchantWithPlan();
    $user->forceFill([
        'created_at' => now()->subMonths(8),
        'last_active_at' => now()->subMonths(6),
    ])->save();

    $result = app(EmailNotificationService::class)->queueAllPaymentReminders($user);

    expect($result['inactive'])->toBeTrue()
        ->and($result['queued'])->toBeFalse()
        ->and($result['total_emails'])->toBe(0);
});

it('documents the 5-month inactivity rule in legal pages', function () {
    $privacy = $this->getJson('/api/legal/privacy')->assertOk()->json('data');
    $terms = $this->getJson('/api/legal/terms')->assertOk()->json('data');

    $privacyText = collect($privacy['sections'] ?? [])->pluck('body')->implode(' ');
    $termsText = collect($terms['sections'] ?? [])->pluck('heading')->implode(' ');

    expect($privacyText)->toContain('5 أشهر')
        ->and($privacyText)->toContain('عملاء هذا البائع')
        ->and($termsText)->toContain('الحسابات غير النشطة');
});
