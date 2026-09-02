<?php

use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function actingAsPlatformAdmin(): User
{
    $admin = User::factory()->create([
        'role' => UserRole::Owner,
        'is_platform_admin' => true,
    ]);

    Sanctum::actingAs($admin);

    return $admin;
}

it('broadcasts a system announcement to every regular user when no user_ids are sent', function () {
    actingAsPlatformAdmin();

    $first = merchantWithPlan();
    $second = merchantWithPlan();

    $this->postJson('/api/admin/system/broadcast-notification', [
        'title' => 'إعلان عام',
        'message' => 'نص الإعلان',
        'type' => 'system_announcement',
    ])
        ->assertOk()
        ->assertJsonPath('data.queued', true);

    expect(Notification::query()->where('user_id', $first->id)->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $second->id)->count())->toBe(1);
});

it('sends an announcement only to the selected users', function () {
    actingAsPlatformAdmin();

    $selected = merchantWithPlan();
    $other = merchantWithPlan();

    $this->postJson('/api/admin/system/broadcast-notification', [
        'title' => 'إعلان خاص',
        'message' => 'هذا الإعلان لمستخدم محدد',
        'type' => 'system_announcement',
        'user_ids' => [$selected->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.queued', true)
        ->assertJsonPath('data.recipient_count', 1);

    expect(Notification::query()->where('user_id', $selected->id)->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $other->id)->count())->toBe(0);
});

it('rejects targeted announcements that include users who are not regular users', function () {
    actingAsPlatformAdmin();

    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->postJson('/api/admin/system/broadcast-notification', [
        'title' => 'إعلان',
        'message' => 'نص',
        'type' => 'system_announcement',
        'user_ids' => [$owner->id],
    ])->assertUnprocessable();

    expect(Notification::query()->count())->toBe(0);
});
