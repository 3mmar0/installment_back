<?php

use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;

it('creates a new month when a partial payment has no later item', function () {
    $merchant = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($merchant)->create();
    $installment = Installment::factory()->forCustomer($customer)->create(['months' => 1]);
    $item = InstallmentItem::factory()->forInstallment($installment)->create([
        'amount' => 1000.00,
        'due_date' => now()->toDateString(),
    ]);

    $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => 400])
        ->assertOk();

    $installment = $item->installment->fresh(['items']);
    $items = $installment->items->sortBy('due_date')->values();

    expect($items)->toHaveCount(2)
        ->and($items[0]->status)->toBe('paid')
        ->and((float) $items[0]->paid_amount)->toBe(400.00)
        ->and($items[1]->status)->toBe('pending')
        ->and((float) $items[1]->amount)->toBe(600.00)
        ->and($installment->months)->toBe(2);
});

it('marks selected months as paid when creating an installment', function () {
    $merchant = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($merchant)->create();

    $response = $this->postJson('/api/installment-create', [
        'customer_id' => $customer->id,
        'total_amount' => 1200,
        'months' => 3,
        'start_date' => now()->toDateString(),
        'prepaid_months' => [1, 3],
    ]);

    $response->assertCreated();

    $installment = Installment::query()->findOrFail($response->json('data.id'));
    $items = $installment->items()->orderBy('due_date')->orderBy('id')->get()->values();

    expect($items)->toHaveCount(3)
        ->and($items[0]->status)->toBe('paid')
        ->and($items[1]->status)->toBe('pending')
        ->and($items[2]->status)->toBe('paid')
        ->and($installment->status)->toBe('active');
});

it('rejects prepaid months outside the plan length', function () {
    $merchant = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($merchant)->create();

    $this->postJson('/api/installment-create', [
        'customer_id' => $customer->id,
        'total_amount' => 1200,
        'months' => 3,
        'start_date' => now()->toDateString(),
        'prepaid_months' => [4],
    ])->assertStatus(422);
});
