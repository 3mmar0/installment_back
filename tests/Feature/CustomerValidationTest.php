<?php

use App\Models\Customer;

it('creates a customer with only a name', function () {
    $merchant = actingAsMerchant();

    $this->postJson('/api/customer-create', ['name' => 'Ahmed'])
        ->assertCreated();

    $customer = Customer::where('user_id', $merchant->id)->sole();

    expect($customer->email)->toBeNull()
        ->and($customer->phone)->toBeNull();
});

it('updates a customer that has no email or phone without supplying them', function () {
    $merchant = actingAsMerchant();

    $customer = Customer::factory()->forMerchant($merchant)->create([
        'email' => null,
        'phone' => null,
    ]);

    $this->putJson("/api/customer-update/{$customer->id}", ['name' => 'Updated'])
        ->assertOk();

    expect($customer->fresh()->name)->toBe('Updated');
});

it('allows clearing a customer email', function () {
    $merchant = actingAsMerchant();

    $customer = Customer::factory()->forMerchant($merchant)->create([
        'email' => 'someone@example.com',
    ]);

    $this->putJson("/api/customer-update/{$customer->id}", ['email' => null])
        ->assertOk();

    expect($customer->fresh()->email)->toBeNull();
});

it('still rejects an invalid email on update', function () {
    $merchant = actingAsMerchant();

    $customer = Customer::factory()->forMerchant($merchant)->create();

    $this->putJson("/api/customer-update/{$customer->id}", ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});
