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

test('customer without installments has no recalculated score snapshot', function () {
    Carbon::setTestNow('2026-09-28 12:00:00');

    $user = merchantWithPlan();
    $customer = Customer::factory()->forMerchant($user)->create();

    $service = app(\App\Services\CreditScore\CreditScoreService::class);
    $snapshot = $service->recalculate($customer);

    expect($snapshot)->toBeNull()
        ->and($customer->fresh()->current_credit_score_id)->toBeNull();

    Carbon::setTestNow();
});

test('profile api returns installment eligibility and null score without installments', function () {
    Carbon::setTestNow('2026-09-28 12:00:00');

    $user = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($user)->create([
        'monthly_salary' => 15000,
    ]);

    $response = $this->getJson("/api/credit-score/customer/{$customer->id}");

    $response->assertOk()
        ->assertJsonPath('data.score', null)
        ->assertJsonPath('data.score_applicable', false)
        ->assertJsonPath('data.installment_eligibility.can_accept_installment', true)
        ->assertJsonPath('data.installment_eligibility.monthly_salary', 15000);

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
                'installment_eligibility',
            ],
        ]);

    expect($response->json('data.disclaimer'))->toContain('تقييم ائتماني داخلي');

    Carbon::setTestNow();
});
