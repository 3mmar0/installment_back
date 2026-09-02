<?php

use App\Models\Customer;
use App\Models\Installment;

it('rejects an installment created against another merchants customer', function () {
    $victim = merchantWithPlan();
    $victimCustomer = Customer::factory()->forMerchant($victim)->create();

    actingAsMerchant();

    $this->postJson('/api/installment-create', [
        'customer_id' => $victimCustomer->id,
        'total_amount' => 5000,
        'months' => 5,
        'start_date' => now()->toDateString(),
    ])->assertStatus(422)
        ->assertJsonValidationErrors('customer_id');

    expect(Installment::count())->toBe(0);
});

it('allows an installment created against the merchants own customer', function () {
    $merchant = actingAsMerchant();
    $ownCustomer = Customer::factory()->forMerchant($merchant)->create();

    $this->postJson('/api/installment-create', [
        'customer_id' => $ownCustomer->id,
        'total_amount' => 5000,
        'months' => 5,
        'start_date' => now()->toDateString(),
    ])->assertCreated();

    expect(Installment::where('user_id', $merchant->id)->count())->toBe(1);
});

it('rejects an installment against a customer that does not exist', function () {
    actingAsMerchant();

    $this->postJson('/api/installment-create', [
        'customer_id' => 999999,
        'total_amount' => 5000,
        'months' => 5,
        'start_date' => now()->toDateString(),
    ])->assertStatus(422);
});

it('never produces an installment whose owner differs from its customers owner', function () {
    $victim = merchantWithPlan();
    $victimCustomer = Customer::factory()->forMerchant($victim)->create();

    actingAsMerchant();

    $this->postJson('/api/installment-create', [
        'customer_id' => $victimCustomer->id,
        'total_amount' => 5000,
        'months' => 5,
        'start_date' => now()->toDateString(),
    ]);

    $mismatched = Installment::join('customers', 'installments.customer_id', '=', 'customers.id')
        ->whereColumn('installments.user_id', '!=', 'customers.user_id')
        ->count();

    expect($mismatched)->toBe(0);
});
