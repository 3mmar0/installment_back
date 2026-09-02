<?php

use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;

it('denies cross-tenant access across customer routes', function () {
    $victim = merchantWithPlan();
    actingAsMerchant();

    $customer = Customer::factory()->forMerchant($victim)->create();

    $this->getJson("/api/customer-show/{$customer->id}")->assertForbidden();
    $this->putJson("/api/customer-update/{$customer->id}", ['name' => 'Hacked'])->assertForbidden();
    $this->deleteJson("/api/customer-delete/{$customer->id}")->assertForbidden();
    $this->getJson("/api/customer-stats/{$customer->id}")->assertForbidden();
    $this->postJson("/api/customer-send-reminders/{$customer->id}")->assertForbidden();

    expect(Customer::find($customer->id)?->name)->not->toBe('Hacked');
});

it('denies cross-tenant access across installment routes', function () {
    $victim = merchantWithPlan();
    actingAsMerchant();

    $customer = Customer::factory()->forMerchant($victim)->create();
    $installment = Installment::factory()->forCustomer($customer)->create();
    $item = InstallmentItem::factory()->forInstallment($installment)->create();

    $this->getJson("/api/installment-show/{$installment->id}")->assertForbidden();
    $this->putJson("/api/installment-update/{$installment->id}", ['name' => 'Hacked'])->assertForbidden();
    $this->deleteJson("/api/installment-delete/{$installment->id}")->assertForbidden();
    $this->getJson("/api/installment-stats/{$installment->id}")->assertForbidden();
    $this->postJson("/api/installment-remind/{$installment->id}")->assertForbidden();
    $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => $item->amount])
        ->assertForbidden();

    expect($installment->fresh()->name)->not->toBe('Hacked')
        ->and($item->fresh()->status)->toBe('pending');
});

it('allows a merchant to access their own customer and installment records', function () {
    $merchant = actingAsMerchant();

    $customer = Customer::factory()->forMerchant($merchant)->create();
    $installment = Installment::factory()->forCustomer($customer)->create();
    $item = InstallmentItem::factory()->forInstallment($installment)->create(['amount' => 500]);

    $this->getJson("/api/customer-show/{$customer->id}")->assertOk();
    $this->getJson("/api/installment-show/{$installment->id}")->assertOk();
    $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => 500])->assertOk();
});
