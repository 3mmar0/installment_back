<?php

use App\Enums\ComplaintStatus;
use App\Enums\UserRole;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
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

it('lets an owner reply to a complaint as a thread message', function () {
    $owner = actingAsOwner();
    $user = merchantWithPlan();

    $complaint = Complaint::query()->create([
        'user_id' => $user->id,
        'subject' => 'شكوى',
        'category' => 'complaint',
        'message' => 'الخدمة بطيئة',
        'status' => ComplaintStatus::Pending,
    ]);
    ComplaintMessage::query()->create([
        'complaint_id' => $complaint->id,
        'user_id' => $user->id,
        'body' => 'الخدمة بطيئة',
        'is_staff' => false,
    ]);

    $this->postJson("/api/complaint-reply/{$complaint->id}", [
        'admin_reply' => 'تم متابعة الطلب',
        'status' => 'replied',
    ])
        ->assertOk()
        ->assertJsonPath('data.admin_reply', 'تم متابعة الطلب')
        ->assertJsonPath('data.status', 'replied')
        ->assertJsonPath('data.messages.1.body', 'تم متابعة الطلب')
        ->assertJsonPath('data.messages.1.is_staff', true);

    expect($complaint->fresh()->replied_by)->toBe($owner->id)
        ->and($complaint->messages()->count())->toBe(2);
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

it('creates a ticket with an initial message and rejects duplicate submits', function () {
    $user = merchantWithPlan();
    Sanctum::actingAs($user);

    $payload = [
        'subject' => 'مشكلة متكررة',
        'category' => 'support',
        'message' => 'نفس الرسالة بالظبط',
        'client_request_id' => 'req-abc-123',
    ];

    $first = $this->postJson('/api/complaint-create', $payload)
        ->assertCreated()
        ->json('data');

    expect($first['messages'])->toHaveCount(1)
        ->and($first['messages'][0]['body'])->toBe('نفس الرسالة بالظبط')
        ->and($first['messages'][0]['is_staff'])->toBeFalse();

    $second = $this->postJson('/api/complaint-create', $payload)
        ->assertOk()
        ->json('data');

    expect($second['id'])->toBe($first['id'])
        ->and(Complaint::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('lets the author send a follow-up message and reopen the ticket', function () {
    $user = merchantWithPlan();
    $owner = User::factory()->create([
        'role' => UserRole::Owner,
        'is_platform_admin' => false,
    ]);

    $complaint = Complaint::query()->create([
        'user_id' => $user->id,
        'subject' => 'متابعة',
        'category' => 'support',
        'message' => 'أول رسالة',
        'status' => ComplaintStatus::Replied,
        'admin_reply' => 'رد الإدارة',
        'replied_by' => $owner->id,
        'replied_at' => now(),
    ]);
    ComplaintMessage::query()->create([
        'complaint_id' => $complaint->id,
        'user_id' => $user->id,
        'body' => 'أول رسالة',
        'is_staff' => false,
    ]);
    ComplaintMessage::query()->create([
        'complaint_id' => $complaint->id,
        'user_id' => $owner->id,
        'body' => 'رد الإدارة',
        'is_staff' => true,
    ]);

    Sanctum::actingAs($user);

    $this->postJson("/api/complaint-message/{$complaint->id}", [
        'body' => 'لسه المشكلة موجودة',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.messages.2.body', 'لسه المشكلة موجودة')
        ->assertJsonPath('data.messages.2.is_staff', false);

    expect($complaint->fresh()->status)->toBe(ComplaintStatus::Pending);
});

it('blocks messages on closed tickets', function () {
    $user = merchantWithPlan();
    $complaint = Complaint::query()->create([
        'user_id' => $user->id,
        'subject' => 'مغلقة',
        'category' => 'other',
        'message' => 'تم',
        'status' => ComplaintStatus::Closed,
    ]);

    Sanctum::actingAs($user);

    $this->postJson("/api/complaint-message/{$complaint->id}", [
        'body' => 'محاولة',
    ])->assertStatus(422);
});
