<?php

use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;

it('returns wave1 collection reports in catalog when reports enabled', function () {
    actingAsMerchant();

    $this->getJson('/api/reports/catalog')
        ->assertOk()
        ->assertJsonPath('data.financial_enabled', true)
        ->assertJsonFragment(['key' => 'overdue'])
        ->assertJsonFragment(['key' => 'aging'])
        ->assertJsonFragment(['key' => 'due_soon']);
});

it('forbids report show when reports feature disabled', function () {
    actingAsMerchant(['reports' => false]);

    $this->getJson('/api/reports/overdue')
        ->assertStatus(403);
});

it('returns overdue report rows for the authenticated vendor only', function () {
    $user = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($user)->create(['name' => 'عميل التقرير']);
    $installment = Installment::factory()->forCustomer($customer)->create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);
    InstallmentItem::factory()->forInstallment($installment)->overdue()->create([
        'amount' => 150,
        'due_date' => now()->subDays(10)->toDateString(),
    ]);

    $other = merchantWithPlan();
    $otherCustomer = Customer::factory()->forMerchant($other)->create();
    $otherInstallment = Installment::factory()->forCustomer($otherCustomer)->create([
        'user_id' => $other->id,
        'status' => 'active',
    ]);
    InstallmentItem::factory()->forInstallment($otherInstallment)->overdue()->create([
        'amount' => 999,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    $response = $this->getJson('/api/reports/overdue?per_page=50')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => ['count', 'total_amount'],
                'columns',
                'rows',
                'meta' => ['total', 'page', 'per_page'],
            ],
        ]);

    $rows = $response->json('data.rows');
    expect($rows)->toHaveCount(1)
        ->and((float) $response->json('data.summary.total_amount'))->toBe(150.0)
        ->and(collect($rows)->pluck('amount')->map(fn ($v) => (float) $v)->all())->not->toContain(999.0);
});

it('returns aging buckets summary', function () {
    $user = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($user)->create();
    $installment = Installment::factory()->forCustomer($customer)->create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);
    InstallmentItem::factory()->forInstallment($installment)->overdue()->create([
        'amount' => 100,
        'due_date' => now()->subDays(3)->toDateString(),
    ]);
    InstallmentItem::factory()->forInstallment($installment)->overdue()->create([
        'amount' => 200,
        'due_date' => now()->subDays(45)->toDateString(),
    ]);

    $this->getJson('/api/reports/aging')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => ['buckets'],
                'columns',
                'rows',
                'meta',
            ],
        ]);
});

it('returns due_soon rows within days filter', function () {
    $user = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($user)->create();
    $installment = Installment::factory()->forCustomer($customer)->create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);
    InstallmentItem::factory()->forInstallment($installment)->create([
        'amount' => 80,
        'status' => 'pending',
        'due_date' => now()->addDays(5)->toDateString(),
    ]);
    InstallmentItem::factory()->forInstallment($installment)->create([
        'amount' => 90,
        'status' => 'pending',
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $this->getJson('/api/reports/due_soon?days=14')
        ->assertOk()
        ->assertJsonPath('data.meta.per_page', 20);

    $rows = $this->getJson('/api/reports/due_soon?days=14')->json('data.rows');
    expect(collect($rows)->pluck('amount')->map(fn ($v) => (float) $v)->all())
        ->toContain(80.0)
        ->not->toContain(90.0);
});
