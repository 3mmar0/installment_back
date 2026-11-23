<?php

use App\Models\ClientAccount;
use App\Models\Customer;

it('forbids non platform admins from listing client accounts', function () {
    $merchant = merchantWithPlan();

    $this->actingAs($merchant, 'sanctum')
        ->getJson('/api/admin/client-accounts')
        ->assertForbidden();
});

it('lists client accounts for platform admins with linked customer counts', function () {
    actingAsPlatformAdmin();

    $merchant = merchantWithPlan();

    $linked = ClientAccount::create([
        'name' => 'عميل مرتبط',
        'email' => 'linked-client@example.com',
        'phone' => '01011112222',
        'phone_normalized' => '1011112222',
        'password' => 'Password123!',
        'email_verified_at' => now(),
    ]);

    Customer::factory()->forMerchant($merchant)->create([
        'client_account_id' => $linked->id,
        'phone' => '01011112222',
    ]);

    ClientAccount::create([
        'name' => 'عميل يتيم',
        'email' => 'orphan-client@example.com',
        'phone' => '01033334444',
        'phone_normalized' => '1033334444',
        'password' => 'Password123!',
        'email_verified_at' => null,
    ]);

    $this->getJson('/api/admin/client-accounts?linked=yes')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.data.0.email', 'linked-client@example.com')
        ->assertJsonPath('data.data.0.customers_count', 1);

    $this->getJson('/api/admin/client-accounts?linked=no&verified=no')
        ->assertOk()
        ->assertJsonPath('data.data.0.email', 'orphan-client@example.com')
        ->assertJsonPath('data.data.0.customers_count', 0)
        ->assertJsonPath('data.data.0.is_verified', false);
});

it('shows a client account with linked merchants and unlinks customers on delete', function () {
    actingAsPlatformAdmin();

    $merchant = merchantWithPlan();

    $client = ClientAccount::create([
        'name' => 'حساب للحذف',
        'email' => 'delete-me@example.com',
        'phone' => '01055556666',
        'phone_normalized' => '1055556666',
        'password' => 'Password123!',
        'email_verified_at' => now(),
    ]);

    $customer = Customer::factory()->forMerchant($merchant)->create([
        'client_account_id' => $client->id,
        'phone' => '01055556666',
    ]);

    $this->getJson('/api/admin/client-accounts/'.$client->id)
        ->assertOk()
        ->assertJsonPath('data.email', 'delete-me@example.com')
        ->assertJsonPath('data.customers.0.id', $customer->id)
        ->assertJsonPath('data.customers.0.merchant.id', $merchant->id);

    $this->deleteJson('/api/admin/client-accounts/'.$client->id)
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(ClientAccount::query()->find($client->id))->toBeNull()
        ->and($customer->fresh()->client_account_id)->toBeNull();
});

it('searches client accounts by email or phone', function () {
    actingAsPlatformAdmin();

    ClientAccount::create([
        'name' => 'بحث',
        'email' => 'find-me@example.com',
        'phone' => '01077778888',
        'phone_normalized' => '1077778888',
        'password' => 'Password123!',
        'email_verified_at' => now(),
    ]);

    ClientAccount::create([
        'name' => 'آخر',
        'email' => 'other@example.com',
        'phone' => '01099990000',
        'phone_normalized' => '1099990000',
        'password' => 'Password123!',
        'email_verified_at' => now(),
    ]);

    $this->getJson('/api/admin/client-accounts?search=find-me@example.com')
        ->assertOk()
        ->assertJsonPath('data.data.0.email', 'find-me@example.com')
        ->assertJsonPath('data.meta.total', 1);
});
