<?php

use App\Jobs\ProcessScheduledRemindersJob;
use App\Mail\PaymentDueReminderBatch;
use App\Mail\PaymentOverdueNoticeBatch;
use App\Models\ClientAccount;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

function seedMerchantWithOverdueAndDueSoon(): array
{
    $user = merchantWithPlan();
    $customer = Customer::factory()->forMerchant($user)->create([
        'email' => 'overdue-customer@example.com',
    ]);
    $installment = Installment::factory()->forCustomer($customer)->create();

    InstallmentItem::factory()->forInstallment($installment)->overdue()->create(['amount' => 100]);
    InstallmentItem::factory()->forInstallment($installment)->overdue()->create(['amount' => 250]);
    InstallmentItem::factory()->forInstallment($installment)->create([
        'due_date' => now()->addDay(),
        'amount' => 50,
    ]);

    return compact('user', 'customer', 'installment');
}

it('creates one merged overdue notification for a merchant with several late items', function () {
    $user = merchantWithPlan();
    $customer = Customer::factory()->forMerchant($user)->create();
    $installment = Installment::factory()->forCustomer($customer)->create();

    InstallmentItem::factory()->forInstallment($installment)->overdue()->count(2)->create();

    $created = app(NotificationService::class)->notifyOverduePayments($user);

    $overdue = Notification::query()
        ->where('user_id', $user->id)
        ->where('type', 'payment_overdue')
        ->get();

    expect($created)->toBe(1)
        ->and($overdue)->toHaveCount(1)
        ->and($overdue->first()->data['merged'])->toBeTrue()
        ->and($overdue->first()->data['count'])->toBe(2);
});

it('does not send overdue emails or notifications on a non-digest weekday', function () {
    config(['mail.notifications_enabled' => true]);
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-09-22 08:00:00', 'UTC')); // Tuesday

    $seeded = seedMerchantWithOverdueAndDueSoon();

    (new ProcessScheduledRemindersJob)->handle(app(NotificationService::class));

    expect(
        Notification::query()
            ->where('user_id', $seeded['user']->id)
            ->where('type', 'payment_overdue')
            ->count()
    )->toBe(0);

    Mail::assertNotSent(PaymentOverdueNoticeBatch::class);
    Mail::assertSent(PaymentDueReminderBatch::class, 1);
});

it('sends one merged overdue email and one overdue notification on the digest weekday', function () {
    config(['mail.notifications_enabled' => true]);
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-09-21 08:00:00', 'UTC')); // Monday

    $seeded = seedMerchantWithOverdueAndDueSoon();

    (new ProcessScheduledRemindersJob)->handle(app(NotificationService::class));

    $overdue = Notification::query()
        ->where('user_id', $seeded['user']->id)
        ->where('type', 'payment_overdue')
        ->get();

    expect($overdue)->toHaveCount(1)
        ->and($overdue->first()->data['merged'])->toBeTrue()
        ->and($overdue->first()->data['count'])->toBe(2);

    Mail::assertSent(PaymentOverdueNoticeBatch::class, 1);
    Mail::assertSent(PaymentDueReminderBatch::class, 1);
});

it('sends clients one weekly merged overdue notification instead of one per item', function () {
    $this->travelTo(Carbon::parse('2026-09-21 08:00:00', 'UTC')); // Monday

    $user = merchantWithPlan();
    $client = ClientAccount::query()->create([
        'name' => 'عميل متأخر',
        'email' => 'late-client@example.com',
        'phone' => '01011111111',
        'phone_normalized' => '01011111111',
        'password' => 'password',
        'email_verified_at' => now(),
    ]);
    $customer = Customer::factory()->forMerchant($user)->create([
        'client_account_id' => $client->id,
        'email' => $client->email,
    ]);
    $installment = Installment::factory()->forCustomer($customer)->create();
    InstallmentItem::factory()->forInstallment($installment)->overdue()->count(3)->create();

    (new ProcessScheduledRemindersJob)->handle(app(NotificationService::class));

    $overdue = Notification::query()
        ->where('client_account_id', $client->id)
        ->where('type', 'payment_overdue')
        ->get();

    expect($overdue)->toHaveCount(1)
        ->and($overdue->first()->data['merged'])->toBeTrue()
        ->and($overdue->first()->data['count'])->toBe(3);
});

it('does not notify clients about overdue installments on a non-digest weekday', function () {
    $this->travelTo(Carbon::parse('2026-09-22 08:00:00', 'UTC')); // Tuesday

    $user = merchantWithPlan();
    $client = ClientAccount::query()->create([
        'name' => 'عميل متأخر',
        'email' => 'late-client-tue@example.com',
        'phone' => '01022222222',
        'phone_normalized' => '01022222222',
        'password' => 'password',
        'email_verified_at' => now(),
    ]);
    $customer = Customer::factory()->forMerchant($user)->create([
        'client_account_id' => $client->id,
    ]);
    $installment = Installment::factory()->forCustomer($customer)->create();
    InstallmentItem::factory()->forInstallment($installment)->overdue()->count(2)->create();

    (new ProcessScheduledRemindersJob)->handle(app(NotificationService::class));

    expect(
        Notification::query()
            ->where('client_account_id', $client->id)
            ->where('type', 'payment_overdue')
            ->count()
    )->toBe(0);
});
