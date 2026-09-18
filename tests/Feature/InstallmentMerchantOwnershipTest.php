<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('stores an owner-created installment on the customer merchant and lets that merchant open it', function () {
    $merchant = merchantWithPlan();
    $customer = Customer::factory()->forMerchant($merchant)->create();
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    Sanctum::actingAs($owner);

    $this->postJson('/api/installment-create', [
        'customer_id' => $customer->id,
        'name' => 'اسم المنتج',
        'total_amount' => 400,
        'months' => 4,
        'start_date' => now()->toDateString(),
    ])->assertCreated();

    $installment = Installment::query()->where('customer_id', $customer->id)->sole();

    expect($installment->user_id)->toBe($merchant->id);

    Sanctum::actingAs($merchant);

    $this->getJson("/api/installment-show/{$installment->id}")->assertOk();
    $this->getJson('/api/installment-list')->assertOk()->assertJsonFragment(['id' => $installment->id]);
});

it('lets a merchant open and list an installment on their customer even if user_id belongs to another account', function () {
    $merchant = merchantWithPlan();
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $customer = Customer::factory()->forMerchant($merchant)->create();
    $installment = Installment::factory()->forCustomer($customer)->create([
        'user_id' => $owner->id,
        'name' => 'اسم المنتج',
    ]);

    Sanctum::actingAs($merchant);

    $item = InstallmentItem::factory()->forInstallment($installment)->create(['amount' => 100]);

    $this->getJson("/api/installment-show/{$installment->id}")->assertOk();
    $this->getJson('/api/installment-list')->assertOk()->assertJsonFragment(['id' => $installment->id]);
    $this->getJson("/api/customer-show/{$customer->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $installment->id]);
    $this->postJson("/api/installment-item-pay/{$item->id}", ['paid_amount' => 100])->assertOk();
});
