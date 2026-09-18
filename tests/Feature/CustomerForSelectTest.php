<?php

use App\Models\Customer;
use Laravel\Sanctum\Sanctum;

it('returns every merchant customer for the select list and finds them by email', function () {
    $merchant = merchantWithPlan();
    Customer::factory()->forMerchant($merchant)->count(25)->create();
    $target = Customer::factory()->forMerchant($merchant)->create([
        'name' => 'Hidden Client',
        'email' => 'hidden-client@example.com',
        'phone' => '01000000000',
    ]);

    Sanctum::actingAs($merchant);

    $this->getJson('/api/customer-for-select')
        ->assertOk()
        ->assertJsonPath('data.data', fn ($rows) => is_array($rows) && count($rows) >= 26)
        ->assertJsonFragment([
            'id' => $target->id,
            'email' => 'hidden-client@example.com',
        ]);

    $this->getJson('/api/customer-for-select?search=hidden-client@example.com')
        ->assertOk()
        ->assertJsonFragment(['id' => $target->id]);
});
