<?php

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;

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
