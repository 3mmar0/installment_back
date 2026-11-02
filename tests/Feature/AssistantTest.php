<?php

use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Services\Assistant\AssistantToolRunner;
use App\Services\Assistant\PendingActionStore;
use Illuminate\Support\Facades\Http;

it('rejects assistant message from unauthenticated callers', function () {
    $this->postJson('/api/assistant/message', [
        'message' => 'ضيف عميل',
    ])->assertUnauthorized();
});

it('returns assistant status for a merchant', function () {
    config([
        'assistant.enabled' => true,
        'assistant.gemini.api_key' => 'test-key',
        'assistant.daily_message_limit' => 30,
    ]);

    actingAsMerchant();

    $this->getJson('/api/assistant/status')
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.configured', true)
        ->assertJsonPath('data.daily_limit', 30);
});

it('does not create a customer until the pending action is confirmed', function () {
    $merchant = actingAsMerchant();
    $runner = app(AssistantToolRunner::class);

    $proposed = $runner->run('propose_create_customer', [
        'name' => 'أحمد تجريبي',
        'phone' => '01000000001',
    ], $merchant);

    expect($proposed['ok'])->toBeTrue()
        ->and($proposed['pending_action']['id'] ?? null)->not->toBeEmpty()
        ->and(Customer::query()->where('name', 'أحمد تجريبي')->exists())->toBeFalse();

    $pendingId = $proposed['pending_action']['id'];
    $pending = app(PendingActionStore::class)->get($merchant->id, $pendingId);
    expect($pending)->not->toBeNull();

    $confirmed = $runner->confirm($pending, $merchant);

    expect($confirmed['ok'])->toBeTrue()
        ->and($confirmed['navigation']['screen'] ?? null)->toBe('CustomerDetail')
        ->and(Customer::query()->where('name', 'أحمد تجريبي')->exists())->toBeTrue();
});

it('creates an installment only after confirm', function () {
    $merchant = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($merchant)->create(['name' => 'سارة']);
    $runner = app(AssistantToolRunner::class);

    $proposed = $runner->run('propose_create_installment', [
        'customer_id' => $customer->id,
        'total_amount' => 1200,
        'months' => 3,
        'start_date' => now()->toDateString(),
    ], $merchant);

    expect($proposed['ok'])->toBeTrue()
        ->and(Installment::query()->where('customer_id', $customer->id)->count())->toBe(0);

    $pending = app(PendingActionStore::class)->get($merchant->id, $proposed['pending_action']['id']);
    $confirmed = $runner->confirm($pending, $merchant);

    expect($confirmed['ok'])->toBeTrue()
        ->and(Installment::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and($confirmed['navigation']['screen'] ?? null)->toBe('InstallmentDetail');
});

it('pays an installment item only after confirm', function () {
    $merchant = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($merchant)->create();
    $installment = Installment::factory()->forCustomer($customer)->create(['months' => 1]);
    $item = InstallmentItem::factory()->forInstallment($installment)->create([
        'amount' => 500,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
    ]);
    $runner = app(AssistantToolRunner::class);

    $proposed = $runner->run('propose_pay_installment_item', [
        'item_id' => $item->id,
        'paid_amount' => 200,
        'carry_remainder' => false,
    ], $merchant);

    expect($proposed['ok'])->toBeTrue()
        ->and($item->fresh()->status)->toBe('pending');

    $pending = app(PendingActionStore::class)->get($merchant->id, $proposed['pending_action']['id']);
    $confirmed = $runner->confirm($pending, $merchant);

    expect($confirmed['ok'])->toBeTrue()
        ->and($item->fresh()->status)->toBe('partial')
        ->and((float) $item->fresh()->paid_amount)->toBe(200.0);
});

it('asks when customer search is ambiguous for installment create', function () {
    $merchant = actingAsMerchant();
    Customer::factory()->forMerchant($merchant)->create(['name' => 'محمد علي']);
    Customer::factory()->forMerchant($merchant)->create(['name' => 'محمد حسن']);
    $runner = app(AssistantToolRunner::class);

    $result = $runner->run('propose_create_installment', [
        'customer_query' => 'محمد',
        'total_amount' => 1000,
        'months' => 2,
    ], $merchant);

    expect($result['ok'])->toBeTrue()
        ->and($result['result']['ambiguous'] ?? false)->toBeTrue()
        ->and($result['result']['customers'] ?? [])->toHaveCount(2)
        ->and($result['pending_action'] ?? null)->toBeNull();
});

it('cancels a pending action through the confirm endpoint', function () {
    $merchant = actingAsMerchant();
    $runner = app(AssistantToolRunner::class);
    $proposed = $runner->run('propose_create_customer', [
        'name' => 'لن يُنشأ',
    ], $merchant);

    $this->postJson('/api/assistant/confirm', [
        'pending_action_id' => $proposed['pending_action']['id'],
        'confirmed' => false,
    ])->assertOk()
        ->assertJsonPath('data.reply', 'تم إلغاء العملية.');

    expect(Customer::query()->where('name', 'لن يُنشأ')->exists())->toBeFalse();
});

it('enforces the daily assistant message limit', function () {
    config([
        'assistant.enabled' => true,
        'assistant.gemini.api_key' => 'test-key',
        'assistant.daily_message_limit' => 1,
    ]);

    Http::fake([
        '*generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => 'أهلاً']],
                ],
            ]],
        ], 200),
    ]);

    actingAsMerchant();

    $this->postJson('/api/assistant/message', ['message' => 'مساعدة'])
        ->assertOk()
        ->assertJsonPath('data.reply', 'أهلاً');

    $this->postJson('/api/assistant/message', ['message' => 'تاني'])
        ->assertOk()
        ->assertJsonPath('data.remaining_today', 0);

    expect($this->postJson('/api/assistant/message', ['message' => 'تالت'])->json('data.reply'))
        ->toContain('وصلت للحد اليومي');
});
