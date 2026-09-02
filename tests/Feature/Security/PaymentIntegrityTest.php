<?php

use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\Notification;

function payableItem(App\Models\User $merchant, float $amount = 1000.00): InstallmentItem
{
    $customer = Customer::factory()->forMerchant($merchant)->create();
    $installment = Installment::factory()->forCustomer($customer)->create();

    return InstallmentItem::factory()
        ->forInstallment($installment)
        ->create(['amount' => $amount]);
}

it('rejects a payment below the scheduled amount', function () {
    $merchant = actingAsMerchant();
    $item = payableItem($merchant, 1000.00);

    $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => 1])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INST_011');

    expect($item->fresh()->status)->toBe('pending');
});

it('rejects a payment above the scheduled amount', function () {
    $merchant = actingAsMerchant();
    $item = payableItem($merchant, 1000.00);

    $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => 5000])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INST_011');

    expect($item->fresh()->status)->toBe('pending');
});

it('rejects a zero payment', function () {
    $merchant = actingAsMerchant();
    $item = payableItem($merchant, 1000.00);

    $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => 0])
        ->assertStatus(422);

    expect($item->fresh()->status)->toBe('pending');
});

it('accepts a payment equal to the scheduled amount', function () {
    $merchant = actingAsMerchant();
    $item = payableItem($merchant, 1000.00);

    $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => 1000])
        ->assertOk();

    $paid = $item->fresh();

    expect($paid->status)->toBe('paid')
        ->and((float) $paid->paid_amount)->toBe(1000.00)
        ->and($paid->paid_at)->not->toBeNull();
});

it('rejects a replayed payment against an already paid item', function () {
    $merchant = actingAsMerchant();
    $item = payableItem($merchant, 1000.00);

    $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => 1000])
        ->assertOk();

    $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => 1000])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INST_010');

    expect(InstallmentItem::where('status', 'paid')->count())->toBe(1);
});

it('creates no notification when a payment is rejected', function () {
    $merchant = actingAsMerchant();
    $item = payableItem($merchant, 1000.00);

    Notification::query()->delete();

    $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => 1])
        ->assertStatus(422);

    expect(Notification::count())->toBe(0);
});

it('records a payment reference even when the client sends none', function () {
    $merchant = actingAsMerchant();
    $item = payableItem($merchant, 1000.00);

    $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => 1000])
        ->assertOk();

    expect($item->fresh()->reference)->not->toBeNull();
});

it('forbids paying an installment item belonging to another merchant', function () {
    $victim = merchantWithPlan();
    $item = payableItem($victim, 1000.00);

    actingAsMerchant();

    $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => 1000])
        ->assertForbidden();

    expect($item->fresh()->status)->toBe('pending');
});

it('completes the installment once every item is paid', function () {
    $merchant = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($merchant)->create();
    $installment = Installment::factory()->forCustomer($customer)->create();

    $items = InstallmentItem::factory()
        ->count(2)
        ->forInstallment($installment)
        ->create(['amount' => 500.00]);

    foreach ($items as $item) {
        $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => 500])
            ->assertOk();
    }

    expect($installment->fresh()->status)->toBe('completed');
});
