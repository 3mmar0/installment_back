<?php

use App\Enums\ComplaintStatus;
use App\Enums\UserRole;
use App\Models\Complaint;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function actingAsOwner(): User
{
    $owner = User::factory()->create([
        'role' => UserRole::Owner,
        'is_platform_admin' => false,
    ]);

    Sanctum::actingAs($owner);

    return $owner;
}

it('lets an owner list every user complaint', function () {
    actingAsOwner();

    $first = merchantWithPlan();
    $second = merchantWithPlan();

    $firstTicket = Complaint::query()->create([
        'user_id' => $first->id,
        'subject' => 'مشكلة دفع',
        'category' => 'billing',
        'message' => 'لم تُسجل الدفعة',
        'status' => ComplaintStatus::Pending,
    ]);
    $secondTicket = Complaint::query()->create([
        'user_id' => $second->id,
        'subject' => 'دعم فني',
        'category' => 'support',
        'message' => 'التطبيق لا يفتح',
        'status' => ComplaintStatus::Pending,
    ]);

    $payload = $this->getJson('/api/complaint-list')->assertOk()->json('data');
    $ids = collect($payload)->pluck('id');

    expect($ids)->toContain($firstTicket->id)
        ->and($ids)->toContain($secondTicket->id);
});

it('lets an owner reply to a complaint', function () {
    $owner = actingAsOwner();
    $user = merchantWithPlan();

    $complaint = Complaint::query()->create([
        'user_id' => $user->id,
        'subject' => 'شكوى',
        'category' => 'complaint',
        'message' => 'الخدمة بطيئة',
        'status' => ComplaintStatus::Pending,
    ]);

    $this->postJson("/api/complaint-reply/{$complaint->id}", [
        'admin_reply' => 'تم متابعة الطلب',
        'status' => 'replied',
    ])
        ->assertOk()
        ->assertJsonPath('data.admin_reply', 'تم متابعة الطلب')
        ->assertJsonPath('data.status', 'replied');

    expect($complaint->fresh()->replied_by)->toBe($owner->id);
});

it('does not let a regular user list other people complaints or reply', function () {
    $author = merchantWithPlan();
    $other = merchantWithPlan();

    $own = Complaint::query()->create([
        'user_id' => $author->id,
        'subject' => 'طلب دعم',
        'category' => 'support',
        'message' => 'أحتاج مساعدة',
        'status' => ComplaintStatus::Pending,
    ]);
    $foreign = Complaint::query()->create([
        'user_id' => $other->id,
        'subject' => 'طلب آخر',
        'category' => 'other',
        'message' => 'رسالة خاصة',
        'status' => ComplaintStatus::Pending,
    ]);

    Sanctum::actingAs($author);

    $ids = collect($this->getJson('/api/complaint-list')->assertOk()->json('data'))
        ->pluck('id');

    expect($ids)->toContain($own->id)
        ->and($ids)->not->toContain($foreign->id);

    $this->postJson("/api/complaint-reply/{$foreign->id}", [
        'admin_reply' => 'محاولة رد',
    ])->assertForbidden();
});
