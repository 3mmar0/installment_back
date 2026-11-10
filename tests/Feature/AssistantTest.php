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
    ]);

    actingAsMerchant([
        'assistant_messages' => ['from' => 0, 'to' => 30],
    ]);

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

it('creates customer with installment and prepaid months only after confirm', function () {
    $merchant = actingAsMerchant();
    $runner = app(AssistantToolRunner::class);
    $startDate = now()->subMonths(2)->toDateString();

    $proposed = $runner->run('propose_create_customer_with_installment', [
        'name' => 'عميل مركّب',
        'phone' => '01001112233',
        'governorate' => 'القاهرة',
        'address' => 'مدينة نصر',
        'total_amount' => 20000,
        'months' => 5,
        'start_date' => $startDate,
        'prepaid_months' => [1, 2],
    ], $merchant);

    expect($proposed['ok'])->toBeTrue()
        ->and($proposed['pending_action']['type'] ?? null)->toBe('create_customer_with_installment')
        ->and(Customer::query()->where('name', 'عميل مركّب')->exists())->toBeFalse();

    $pending = app(PendingActionStore::class)->get($merchant->id, $proposed['pending_action']['id']);
    $confirmed = $runner->confirm($pending, $merchant);

    expect($confirmed['ok'])->toBeTrue()
        ->and($confirmed['navigation']['screen'] ?? null)->toBe('InstallmentDetail');

    $customer = Customer::query()->where('name', 'عميل مركّب')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->governorate)->toBe('cairo')
        ->and($customer->address)->toBe('مدينة نصر');

    $installment = Installment::query()->where('customer_id', $customer->id)->first();
    expect($installment)->not->toBeNull()
        ->and((float) $installment->total_amount)->toBe(20000.0)
        ->and((int) $installment->months)->toBe(5);

    $items = $installment->items()->orderBy('due_date')->orderBy('id')->get();
    expect($items)->toHaveCount(5)
        ->and($items[0]->status)->toBe('paid')
        ->and($items[1]->status)->toBe('paid')
        ->and($items[2]->status)->toBe('pending');
});

it('supports prepaid months when proposing installment for existing customer', function () {
    $merchant = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($merchant)->create(['name' => 'ليلى']);
    $runner = app(AssistantToolRunner::class);

    $proposed = $runner->run('propose_create_installment', [
        'customer_id' => $customer->id,
        'total_amount' => 1000,
        'months' => 4,
        'start_date' => now()->subMonth()->toDateString(),
        'prepaid_months' => [1],
    ], $merchant);

    $pending = app(PendingActionStore::class)->get($merchant->id, $proposed['pending_action']['id']);
    $confirmed = $runner->confirm($pending, $merchant);

    $installment = Installment::query()->where('customer_id', $customer->id)->first();
    $items = $installment->items()->orderBy('due_date')->orderBy('id')->get();

    expect($confirmed['ok'])->toBeTrue()
        ->and($items[0]->status)->toBe('paid')
        ->and($items[1]->status)->toBe('pending');
});

it('lists customers and summarizes installments', function () {
    $merchant = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($merchant)->create(['name' => 'نور']);
    $installment = Installment::factory()->forCustomer($customer)->create([
        'total_amount' => 900,
        'months' => 3,
    ]);
    InstallmentItem::factory()->forInstallment($installment)->create([
        'amount' => 300,
        'due_date' => now()->subDays(10)->toDateString(),
        'status' => 'pending',
    ]);
    InstallmentItem::factory()->forInstallment($installment)->create([
        'amount' => 300,
        'due_date' => now()->addMonth()->toDateString(),
        'status' => 'pending',
    ]);
    InstallmentItem::factory()->forInstallment($installment)->create([
        'amount' => 300,
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'pending',
    ]);

    $runner = app(AssistantToolRunner::class);

    $listed = $runner->run('list_customers', ['query' => 'نور'], $merchant);
    expect($listed['ok'])->toBeTrue()
        ->and($listed['result']['count'] ?? 0)->toBeGreaterThan(0)
        ->and($listed['navigation']['screen'] ?? null)->toBe('CustomerList');

    $summary = $runner->run('summarize_customer_installments', [
        'customer_id' => $customer->id,
    ], $merchant);

    expect($summary['ok'])->toBeTrue()
        ->and($summary['result']['plans_count'] ?? 0)->toBe(1)
        ->and($summary['result']['overdue_items_count'] ?? 0)->toBe(1)
        ->and($summary['navigation']['screen'] ?? null)->toBe('CustomerDetail');
});

it('opens app screens through open_screen', function () {
    $merchant = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($merchant)->create();
    $runner = app(AssistantToolRunner::class);

    expect($runner->run('open_screen', ['screen' => 'dashboard'], $merchant)['navigation']['screen'] ?? null)
        ->toBe('Dashboard')
        ->and($runner->run('open_screen', ['screen' => 'customers'], $merchant)['navigation']['screen'] ?? null)
        ->toBe('CustomerList')
        ->and($runner->run('open_screen', ['screen' => 'overdue'], $merchant)['navigation']['screen'] ?? null)
        ->toBe('Overdue')
        ->and($runner->run('open_screen', [
            'screen' => 'customer_detail',
            'id' => $customer->id,
        ], $merchant)['navigation'] ?? null)
        ->toMatchArray([
            'screen' => 'CustomerDetail',
            'params' => ['id' => $customer->id],
        ]);
});

it('rejects invalid national id with a clear arabic message', function () {
    $merchant = actingAsMerchant();
    $runner = app(AssistantToolRunner::class);

    $result = $runner->run('propose_create_customer', [
        'name' => 'اختبار',
        'national_id' => '12345',
    ], $merchant);

    expect($result['ok'])->toBeFalse()
        ->and($result['error'] ?? '')->toContain('14');
});

it('resolves relative start dates with Carbon subMonthsNoOverflow in app timezone', function () {
    config(['app.timezone' => 'Africa/Cairo']);
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-03-31 12:00:00', 'Africa/Cairo'));

    $merchant = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($merchant)->create(['name' => 'تاريخ']);
    $runner = app(AssistantToolRunner::class);

    $proposed = $runner->run('propose_create_installment', [
        'customer_id' => $customer->id,
        'total_amount' => 900,
        'months' => 3,
        'start_months_ago' => 3,
    ], $merchant);

    expect($proposed['ok'])->toBeTrue()
        ->and($proposed['pending_action']['payload_preview']['start_date'] ?? null)
        ->toBe('2025-12-31');

    Carbon\Carbon::setTestNow();
});

it('keeps structured session context beyond raw chat turns', function () {
    $merchant = actingAsMerchant();
    $store = app(App\Services\Assistant\AssistantSessionStore::class);
    $sessionId = (string) Illuminate\Support\Str::uuid();

    $store->save($merchant->id, $sessionId, [
        ['role' => 'user', 'text' => 'دور على محمد'],
        ['role' => 'assistant', 'text' => 'لقيت عميل'],
    ], [
        'selected_customer' => ['id' => 55, 'name' => 'محمد', 'phone' => '0100'],
        'draft' => [
            'intent' => 'create_installment',
            'fields' => ['total_amount' => 1000, 'months' => 4],
        ],
    ]);

    $loaded = $store->load($merchant->id, $sessionId);
    $block = $store->contextPromptBlock($loaded['context']);

    expect($loaded['context']['selected_customer']['id'] ?? null)->toBe(55)
        ->and($loaded['context']['draft']['fields']['months'] ?? null)->toBe(4)
        ->and($block)->toContain('العميل المختار حالياً')
        ->and($block)->toContain('مسودة العملية الجارية')
        ->and($block)->toContain('المنطقة الزمنية');
});

it('asks for missing installment fields with needs_input instead of inventing success', function () {
    $merchant = actingAsMerchant();
    $runner = app(AssistantToolRunner::class);

    $result = $runner->run('propose_create_installment', [
        'customer_query' => 'أي حد',
    ], $merchant);

    expect($result['ok'])->toBeTrue()
        ->and($result['result']['status'] ?? null)->toBe('needs_input')
        ->and($result['result']['missing'] ?? [])->toContain('total_amount')
        ->and($result['pending_action'] ?? null)->toBeNull();
});

it('isolates customer tools to the owning merchant', function () {
    $merchantA = actingAsMerchant();
    $merchantB = merchantWithPlan();

    $foreign = Customer::factory()->forMerchant($merchantB)->create(['name' => 'عميل تاني']);
    $runner = app(AssistantToolRunner::class);

    $result = $runner->run('get_customer', [
        'customer_id' => $foreign->id,
    ], $merchantA);

    expect($result['ok'])->toBeFalse()
        ->and($result['error'] ?? '')->not->toBeEmpty();

    $pay = $runner->run('propose_pay_installment_item', [
        'customer_id' => $foreign->id,
        'paid_amount' => 50,
    ], $merchantA);

    expect($pay['ok'])->toBeFalse();
});

it('links confirm results back to the conversation session and is idempotent', function () {
    $merchant = actingAsMerchant();
    $runner = app(AssistantToolRunner::class);
    $orchestrator = app(App\Services\Assistant\AssistantOrchestrator::class);
    $sessionId = (string) Illuminate\Support\Str::uuid();

    $proposed = $runner->run('propose_create_customer', [
        'name' => 'عميل جلسة',
        'phone' => '01002223344',
    ], $merchant, $sessionId);

    $pendingId = $proposed['pending_action']['id'];

    $first = $orchestrator->confirmAction($merchant, $pendingId, true, $sessionId);
    expect($first['ok'] ?? true)->toBeTrue()
        ->and($first['session_id'])->toBe($sessionId)
        ->and($first['reply'])->toContain('نجاح')
        ->and(Customer::query()->where('name', 'عميل جلسة')->count())->toBe(1);

    $second = $orchestrator->confirmAction($merchant, $pendingId, true, $sessionId);
    expect($second['reply'])->toContain('نجاح')
        ->and(Customer::query()->where('name', 'عميل جلسة')->count())->toBe(1);

    $session = app(App\Services\Assistant\AssistantSessionStore::class)->load($merchant->id, $sessionId);
    expect(collect($session['turns'])->pluck('text')->implode(' '))->toContain('تم إضافة العميل')
        ->and($session['context']['last_action_result']['ok'] ?? false)->toBeTrue();
});

it('exposes egyptian dialect guidance and timezone in the system prompt memory', function () {
    $orchestrator = app(App\Services\Assistant\AssistantOrchestrator::class);
    $ref = new ReflectionClass($orchestrator);
    $method = $ref->getMethod('systemPrompt');
    $method->setAccessible(true);

    $prompt = $method->invoke($orchestrator, [
        'timezone' => 'Africa/Cairo',
        'today' => '2026-11-10',
        'selected_customer' => ['id' => 9, 'name' => 'سارة', 'phone' => '011'],
        'draft' => [
            'intent' => 'create_installment',
            'fields' => ['months' => 6, 'total_amount' => 1200],
        ],
    ]);

    expect($prompt)->toContain('اللهجة المصرية')
        ->and($prompt)->toContain('ورّيني العملاء')
        ->and($prompt)->toContain('Africa/Cairo')
        ->and($prompt)->toContain('start_months_ago')
        ->and($prompt)->toContain('ممنوع تقول')
        ->and($prompt)->toContain('العميل المختار حالياً');
});

it('never disables gemini tls verification in production', function () {
    $client = app(App\Services\Assistant\GeminiClient::class);
    $ref = new ReflectionClass($client);
    $method = $ref->getMethod('shouldDisableSslVerify');
    $method->setAccessible(true);

    $previous = app()->environment();
    app()['env'] = 'production';
    config(['assistant.gemini.http_verify' => false]);

    expect($method->invoke($client))->toBeFalse();

    app()['env'] = $previous;
});

it('enforces the monthly assistant message limit from the plan', function () {
    config([
        'assistant.enabled' => true,
        'assistant.gemini.api_key' => 'test-key',
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

    $merchant = actingAsMerchant();
    $limit = $merchant->userLimit;
    $limit->update([
        'assistant_messages' => ['from' => 0, 'to' => 1],
        'assistant_messages_used' => 0,
        'assistant_messages_bonus' => 0,
        'usage_period' => now()->format('Y-m'),
        'features' => array_merge($limit->features ?? [], ['assistant' => true]),
    ]);

    $this->postJson('/api/assistant/message', ['message' => 'مساعدة'])
        ->assertOk()
        ->assertJsonPath('data.reply', 'أهلاً');

    $this->postJson('/api/assistant/message', ['message' => 'تاني'])
        ->assertOk()
        ->assertJsonPath('data.remaining_today', 0);

    expect($this->postJson('/api/assistant/message', ['message' => 'تالت'])->json('data.reply'))
        ->toContain('وصلت للحد الشهري');
});
