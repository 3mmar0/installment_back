<?php

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;

it('does not copy overdue or due-soon notifications to platform admins', function () {
    $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
    $merchant = merchantWithPlan();
    $customer = App\Models\Customer::factory()->forMerchant($merchant)->create();
    $installment = App\Models\Installment::factory()->forCustomer($customer)->create();
    App\Models\InstallmentItem::factory()->forInstallment($installment)->overdue()->create();

    app(NotificationService::class)->notifyOverduePayments($merchant);
    app(NotificationService::class)->notifyUpcomingPayments($merchant);

    expect(Notification::query()->where('user_id', $platformAdmin->id)->count())->toBe(0)
        ->and(Notification::query()->where('user_id', $merchant->id)->where('type', 'payment_overdue')->count())->toBe(1);
});

it('lets a platform admin without a subscription list every merchant customer', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $merchant = merchantWithPlan();
    App\Models\Customer::factory()->forMerchant($merchant)->create(['name' => 'عميل كل التجار']);

    Laravel\Sanctum\Sanctum::actingAs($admin);

    $this->getJson('/api/customer-list')
        ->assertOk()
        ->assertJsonFragment(['name' => 'عميل كل التجار']);
});

it('mirrors merchant notifications to platform administrators without charging the merchant twice', function () {
    $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
    $merchant = merchantWithPlan();

    app(NotificationService::class)->create(
        $merchant,
        'payment_received',
        'تم استلام دفعة',
        'تم استلام دفعة جديدة',
        ['item_id' => 42],
        false
    );

    $adminNotification = Notification::query()
        ->where('user_id', $platformAdmin->id)
        ->sole();

    expect(Notification::query()->where('user_id', $merchant->id)->count())->toBe(1)
        ->and($adminNotification->data['is_platform_admin_copy'])->toBeTrue()
        ->and($adminNotification->data['actor_id'])->toBe($merchant->id);
});
