<?php

use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('gives platform admins one broadcast summary instead of a copy per recipient', function () {
    $admin = actingAsPlatformAdmin();
    $first = merchantWithPlan();
    $second = merchantWithPlan();

    $this->postJson('/api/admin/system/broadcast-notification', [
        'title' => 'تحديث التطبيق',
        'message' => 'نزل النسخة الجديدة',
        'type' => 'mobile_app_update',
    ])->assertOk();

    $adminNotes = Notification::query()->where('user_id', $admin->id)->get();

    expect(Notification::query()->where('user_id', $first->id)->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $second->id)->count())->toBe(1)
        ->and($adminNotes)->toHaveCount(1)
        ->and($adminNotes->first()->data['is_broadcast_summary'])->toBeTrue()
        ->and($adminNotes->first()->data['recipient_count'])->toBe(2)
        ->and($adminNotes->first()->message)->toContain('2')
        ->and($adminNotes->first()->message)->toContain('نزل النسخة الجديدة');
});

it('stores an uploaded video on broadcast notification payload', function () {
    Storage::fake('public');
    actingAsPlatformAdmin();
    $merchant = merchantWithPlan();

    $video = UploadedFile::fake()->create('announce.mp4', 1024, 'video/mp4');

    $this->post('/api/admin/system/broadcast-notification', [
        'title' => 'إعلان بفيديو',
        'message' => 'شاهد الفيديو',
        'type' => 'system_announcement',
        'video' => $video,
        'user_ids' => [$merchant->id],
    ])->assertOk();

    $notification = Notification::query()->where('user_id', $merchant->id)->first();
    $videoUrl = $notification?->data['video_url'] ?? null;

    expect($notification)->not->toBeNull()
        ->and($videoUrl)->toBeString()
        ->and($videoUrl)->toContain('/storage/announcements/')
        ->and($notification->data['display_as'] ?? null)->toBe('modal')
        ->and(Storage::disk('public')->allFiles('announcements'))->not->toBeEmpty();
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
