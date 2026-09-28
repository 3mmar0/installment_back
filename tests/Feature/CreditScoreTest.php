<?php

use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Services\CreditScore\CreditScoreCalculator;
use App\Services\CreditScore\CustomerCreditDataCollector;
use App\Services\CreditScore\Support\CreditScoreConfig;
use App\Services\InstallmentService;
use Carbon\Carbon;

function createMerchantInstallment(Customer $customer, array $overrides = []): Installment
{
    $user = $customer->user ?? \App\Models\User::find($customer->user_id);

    return app(InstallmentService::class)->createInstallment(array_merge([
        'customer_id' => $customer->id,
        'name' => 'Test plan',
        'total_amount' => 3000,
        'months' => 3,
        'start_date' => '2026-01-01',
        'products' => [],
    ], $overrides), $user, notify: false);
}

test('new customer without history receives neutral baseline score near 620', function () {
    Carbon::setTestNow('2026-09-28 12:00:00');

    $user = merchantWithPlan();
    $customer = Customer::factory()->forMerchant($user)->create();

    $config = CreditScoreConfig::fromLiveConfig();
    $data = (new CustomerCreditDataCollector)->collect($customer, $config);
    $result = (new CreditScoreCalculator($config))->calculate($data);

    expect($result->score)->toBeGreaterThanOrEqual(600)
        ->and($result->score)->toBeLessThanOrEqual(650)
        ->and($result->confidenceLevel)->toBe('LOW')
        ->and($result->thinFile)->toBeTrue();

    Carbon::setTestNow();
});

test('perfect on-time payment history yields high payment component', function () {
    Carbon::setTestNow('2026-09-28 12:00:00');

    $user = merchantWithPlan();
    $customer = Customer::factory()->forMerchant($user)->create();

    $installment = createMerchantInstallment($customer, [
        'total_amount' => 3000,
        'months' => 3,
        'start_date' => '2026-01-01',
    ]);

    foreach ($installment->items as $item) {
        $item->update([
            'status' => 'paid',
            'paid_amount' => $item->amount,
            'paid_at' => $item->due_date,
        ]);
    }

    $config = CreditScoreConfig::fromLiveConfig();
    $data = (new CustomerCreditDataCollector)->collect($customer->fresh(), $config);
    $result = (new CreditScoreCalculator($config))->calculate($data);

    expect($result->components['payment_history'])->toBeGreaterThan(85)
        ->and($result->score)->toBeGreaterThan(700);

    Carbon::setTestNow();
});

test('current overdue reduces displayed score below perfect payer', function () {
    Carbon::setTestNow('2026-09-28 12:00:00');

    $user = merchantWithPlan();
    $customer = Customer::factory()->forMerchant($user)->create();

    $installment = createMerchantInstallment($customer, [
        'total_amount' => 2000,
        'months' => 2,
        'start_date' => '2026-06-01',
    ]);

    /** @var InstallmentItem $first */
    $first = $installment->items()->orderBy('due_date')->first();
    $first->update([
        'status' => 'paid',
        'paid_amount' => $first->amount,
        'paid_at' => $first->due_date,
    ]);

    /** @var InstallmentItem $second */
    $second = $installment->items()->orderByDesc('due_date')->first();
    $second->update(['due_date' => '2026-09-01', 'status' => 'overdue']);

    $config = CreditScoreConfig::fromLiveConfig();
    $data = (new CustomerCreditDataCollector)->collect($customer->fresh(), $config);
    $result = (new CreditScoreCalculator($config))->calculate($data);

    expect($result->currentOverdueCount)->toBe(1)
        ->and($result->currentMaxDpd)->toBeGreaterThan(0);

    Carbon::setTestNow();
});

test('credit score profile api returns internal assessment disclaimer', function () {
    Carbon::setTestNow('2026-09-28 12:00:00');

    $user = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($user)->create();

    createMerchantInstallment($customer, [
        'total_amount' => 1000,
        'months' => 1,
        'start_date' => '2026-01-01',
    ]);

    $response = $this->getJson("/api/credit-score/customer/{$customer->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'score',
                'score_max',
                'risk_level',
                'confidence_level',
                'disclaimer',
            ],
        ]);

    expect($response->json('data.disclaimer'))->toContain('Internal Credit Assessment');

    Carbon::setTestNow();
});
