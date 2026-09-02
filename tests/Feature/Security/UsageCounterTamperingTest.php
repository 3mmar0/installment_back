<?php

use App\Models\Customer;

it('does not expose a route for incrementing usage counters', function () {
    actingAsMerchant();

    $this->postJson('/api/limits/increment/customers', ['count' => 1])
        ->assertNotFound();
});

it('does not expose a route for decrementing usage counters', function () {
    actingAsMerchant();

    $this->postJson('/api/limits/decrement/customers', ['count' => 1])
        ->assertNotFound();
});

it('does not let a merchant reset usage to bypass their plan cap', function () {
    $user = actingAsMerchant(['customers' => ['from' => 0, 'to' => 1]]);

    $this->postJson('/api/customer-create', ['name' => 'First'])
        ->assertCreated();

    // The exploit: free up quota by rewinding the counter, then create beyond the cap.
    $this->postJson('/api/limits/decrement/customers', ['count' => 1])
        ->assertNotFound();

    $this->postJson('/api/customer-create', ['name' => 'Second'])
        ->assertForbidden();

    expect(Customer::where('user_id', $user->id)->count())->toBe(1);
});

it('still reports current usage to the client', function () {
    actingAsMerchant();

    $this->getJson('/api/limits/current')->assertOk();
});
