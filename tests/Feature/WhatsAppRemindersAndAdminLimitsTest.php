<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\MerchantGift;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserLimit;
use App\Models\WhatsAppDailyCharge;
use App\Services\PlatformWhatsAppQuota;
use App\Services\WhatsAppCloudApiService;
use App\Services\WhatsAppReminderService;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

it('sends one whatsapp message per installment but charges merchant quota once per customer day', function () {
    config()->set('services.whatsapp', [
        'enabled' => true,
        'phone_number_id' => '123',
        'access_token' => 'token',
        'graph_version' => 'v21.0',
        'app_secret' => null,
        'verify_token' => null,
    ]);
    PlatformWhatsAppQuota::setLimit(100);
    PlatformWhatsAppQuota::setUsed(0);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200),
    ]);

    $merchant = merchantWithPlan([
        'whatsapp_messages' => ['from' => 0, 'to' => 5],
        'whatsapp_messages_used' => 0,
        'whatsapp_messages_bonus' => 0,
        'usage_period' => now()->format('Y-m'),
        'features' => [
            'assistant' => true,
            'whatsapp' => true,
            'basic_reports' => true,
        ],
    ]);
    $merchant->forceFill(['last_active_at' => now()])->save();

    $customer = Customer::factory()->create([
        'user_id' => $merchant->id,
        'phone' => '01000000001',
        'whatsapp_auto_reminders' => true,
    ]);

    $installment = Installment::factory()->create([
        'user_id' => $merchant->id,
        'customer_id' => $customer->id,
        'status' => 'active',
    ]);

    InstallmentItem::factory()->create([
        'installment_id' => $installment->id,
        'amount' => 100,
        'due_date' => now()->subDays(2)->toDateString(),
        'status' => 'pending',
        'paid_at' => null,
    ]);
    InstallmentItem::factory()->create([
        'installment_id' => $installment->id,
        'amount' => 150,
        'due_date' => now()->subDay()->toDateString(),
        'status' => 'pending',
        'paid_at' => null,
    ]);

    $stats = app(WhatsAppReminderService::class)->sendForMerchant($merchant->fresh(), false, true);

    expect($stats['messages_sent'])->toBe(2)
        ->and($stats['quota_charged'])->toBe(1)
        ->and((int) $merchant->fresh()->userLimit->whatsapp_messages_used)->toBe(1)
        ->and(WhatsAppDailyCharge::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(PlatformWhatsAppQuota::snapshot()['used'])->toBe(2);

    // Second run same day should not charge merchant again.
    $stats2 = app(WhatsAppReminderService::class)->sendForMerchant($merchant->fresh(), false, true);
    expect($stats2['quota_charged'])->toBe(0)
        ->and((int) $merchant->fresh()->userLimit->whatsapp_messages_used)->toBe(1);
});

it('skips whatsapp reminders when customer opt-in is off', function () {
    config()->set('services.whatsapp', [
        'enabled' => true,
        'phone_number_id' => '123',
        'access_token' => 'token',
        'graph_version' => 'v21.0',
    ]);
    PlatformWhatsAppQuota::setLimit(50);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200),
    ]);

    $merchant = merchantWithPlan([
        'whatsapp_messages' => ['from' => 0, 'to' => 5],
        'whatsapp_messages_used' => 0,
        'features' => ['whatsapp' => true, 'assistant' => true],
    ]);
    $merchant->forceFill(['last_active_at' => now()])->save();

    $customer = Customer::factory()->create([
        'user_id' => $merchant->id,
        'phone' => '01000000002',
        'whatsapp_auto_reminders' => false,
    ]);

    $installment = Installment::factory()->create([
        'user_id' => $merchant->id,
        'customer_id' => $customer->id,
        'status' => 'active',
    ]);

    InstallmentItem::factory()->create([
        'installment_id' => $installment->id,
        'amount' => 100,
        'due_date' => now()->subDay()->toDateString(),
        'status' => 'pending',
        'paid_at' => null,
    ]);

    $stats = app(WhatsAppReminderService::class)->sendForMerchant($merchant->fresh(), false, true);

    expect($stats['messages_sent'])->toBe(0)
        ->and((int) $merchant->fresh()->userLimit->whatsapp_messages_used)->toBe(0);
    Http::assertNothingSent();
});

it('blocks merchant whatsapp send when platform quota is exhausted', function () {
    config()->set('services.whatsapp', [
        'enabled' => true,
        'phone_number_id' => '123',
        'access_token' => 'token',
        'graph_version' => 'v21.0',
    ]);
    PlatformWhatsAppQuota::setLimit(1);
    PlatformWhatsAppQuota::setUsed(1);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200),
    ]);

    $merchant = merchantWithPlan([
        'whatsapp_messages' => ['from' => 0, 'to' => 10],
        'whatsapp_messages_used' => 0,
        'features' => ['whatsapp' => true],
    ]);

    expect(fn () => app(WhatsAppCloudApiService::class)->sendText('201000000000', 'hi', $merchant))
        ->toThrow(RuntimeException::class);
});

it('syncs plan updates to all active subscribers immediately', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Sanctum::actingAs($owner);

    $plan = Subscription::factory()->create([
        'name' => 'Plan Sync',
        'assistant_messages' => ['from' => 0, 'to' => 3],
        'whatsapp_messages' => ['from' => 0, 'to' => 4],
        'features' => ['assistant' => true, 'whatsapp' => true],
        'created_by' => $owner->id,
    ]);

    $merchant = merchantWithPlan([
        'subscription_id' => $plan->id,
        'subscription_name' => $plan->name,
        'assistant_messages' => ['from' => 0, 'to' => 1],
        'whatsapp_messages' => ['from' => 0, 'to' => 1],
        'features' => ['assistant' => true, 'whatsapp' => true],
    ]);

    $this->putJson("/api/subscriptions-update/{$plan->id}", [
        'assistant_messages' => ['from' => 0, 'to' => 20],
        'whatsapp_messages' => ['from' => 0, 'to' => 30],
    ])->assertOk()
        ->assertJsonPath('data.subscribers_synced', 1);

    $limit = UserLimit::query()->where('user_id', $merchant->id)->first();
    expect($limit->assistant_messages['to'] ?? null)->toBe(20)
        ->and($limit->whatsapp_messages['to'] ?? null)->toBe(30);
});

it('grants bulk gifts and exposes merchant gift history', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Sanctum::actingAs($owner);

    $merchant = merchantWithPlan([
        'assistant_messages' => ['from' => 0, 'to' => 1],
        'assistant_messages_used' => 0,
        'assistant_messages_bonus' => 0,
        'features' => ['assistant' => true],
    ]);

    $this->postJson('/api/admin/gifts/bulk', [
        'user_ids' => [$merchant->id],
        'resource' => 'assistant_messages',
        'amount' => 5,
        'note' => 'هدية جماعية',
    ])->assertOk()
        ->assertJsonPath('data.granted', 1);

    expect((int) $merchant->fresh()->userLimit->assistant_messages_bonus)->toBe(5)
        ->and(MerchantGift::query()->where('user_id', $merchant->id)->count())->toBe(1);

    Sanctum::actingAs($merchant);
    $this->getJson('/api/limits/gifts')
        ->assertOk()
        ->assertJsonPath('data.0.amount', 5)
        ->assertJsonPath('data.0.resource', 'assistant_messages');
});
