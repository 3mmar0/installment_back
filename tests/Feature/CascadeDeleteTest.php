<?php

use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\User;

it('deletes customer installments when the customer is removed', function () {
    $merchant = actingAsMerchant();

    $customer = Customer::factory()->forMerchant($merchant)->create();
    $installment = Installment::factory()->forCustomer($customer)->create();
    $item = InstallmentItem::factory()->forInstallment($installment)->create();

    $this->deleteJson("/api/customer-delete/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Customer::find($customer->id))->toBeNull()
        ->and(Installment::find($installment->id))->toBeNull()
        ->and(InstallmentItem::find($item->id))->toBeNull();
});

it('deletes vendor customers and installments when the vendor is removed', function () {
    actingAsOwner();

    $vendor = merchantWithPlan();
    $customer = Customer::factory()->forMerchant($vendor)->create();
    $installment = Installment::factory()->forCustomer($customer)->create();
    $item = InstallmentItem::factory()->forInstallment($installment)->create();

    $this->deleteJson("/api/user-delete/{$vendor->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(User::find($vendor->id))->toBeNull()
        ->and(Customer::find($customer->id))->toBeNull()
        ->and(Installment::find($installment->id))->toBeNull()
        ->and(InstallmentItem::find($item->id))->toBeNull();
});
