<?php

use App\Models\Customer;
use App\Models\UserLimit;

function usage(int $userId, string $column): int
{
    return (int) UserLimit::where('user_id', $userId)->value($column);
}

it('increments customer usage when a customer is created', function () {
    $user = actingAsMerchant();

    expect(usage($user->id, 'customers_used'))->toBe(0);

    $this->postJson('/api/customer-create', ['name' => 'Ahmed'])->assertCreated();

    expect(usage($user->id, 'customers_used'))->toBe(1);
});

it('decrements customer usage when a customer is deleted', function () {
    $user = actingAsMerchant();

    $customer = Customer::factory()->forMerchant($user)->create();
    $this->postJson('/api/customer-create', ['name' => 'Ahmed'])->assertCreated();

    $before = usage($user->id, 'customers_used');

    $this->deleteJson("/api/customer-delete/{$customer->id}")->assertOk();

    expect(usage($user->id, 'customers_used'))->toBe(max($before - 1, 0));
});

it('increments installment usage when an installment is created', function () {
    $user = actingAsMerchant();

    $customer = Customer::factory()->forMerchant($user)->create();

    expect(usage($user->id, 'installments_used'))->toBe(0);

    $this->postJson('/api/installment-create', [
        'customer_id' => $customer->id,
        'total_amount' => 1200,
        'months' => 4,
        'start_date' => now()->toDateString(),
    ])->assertCreated();

    expect(usage($user->id, 'installments_used'))->toBe(1);
});

it('blocks creation once the plan cap is reached', function () {
    actingAsMerchant(['customers' => ['from' => 0, 'to' => 1]]);

    $this->postJson('/api/customer-create', ['name' => 'First'])->assertCreated();
    $this->postJson('/api/customer-create', ['name' => 'Second'])->assertForbidden();
});
