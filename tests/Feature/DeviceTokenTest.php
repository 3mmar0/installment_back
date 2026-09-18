<?php

use App\Models\ClientAccount;
use App\Models\DeviceToken;
use Laravel\Sanctum\Sanctum;

it('registers a vendor device token', function () {
    $user = actingAsMerchant();

    $this->postJson('/api/device-token', [
        'token' => 'fcm-token-vendor-1',
        'platform' => 'android',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $row = DeviceToken::query()->where('token', 'fcm-token-vendor-1')->sole();

    expect($row->user_id)->toBe($user->id)
        ->and($row->client_account_id)->toBeNull()
        ->and($row->platform)->toBe('android');
});

it('moves an existing token to the newly authenticated owner', function () {
    $first = actingAsMerchant();

    $this->postJson('/api/device-token', [
        'token' => 'shared-fcm-token',
        'platform' => 'android',
    ])->assertOk();

    $second = actingAsMerchant();

    $this->postJson('/api/device-token', [
        'token' => 'shared-fcm-token',
        'platform' => 'android',
    ])->assertOk();

    expect(DeviceToken::query()->where('token', 'shared-fcm-token')->count())->toBe(1)
        ->and(DeviceToken::query()->where('token', 'shared-fcm-token')->value('user_id'))->toBe($second->id)
        ->and(DeviceToken::query()->where('user_id', $first->id)->count())->toBe(0);
});

it('unregisters the current vendor device token', function () {
    actingAsMerchant();

    $this->postJson('/api/device-token', [
        'token' => 'fcm-token-to-delete',
        'platform' => 'android',
    ])->assertOk();

    $this->deleteJson('/api/device-token', [
        'token' => 'fcm-token-to-delete',
    ])->assertOk();

    expect(DeviceToken::query()->where('token', 'fcm-token-to-delete')->exists())->toBeFalse();
});

it('registers and unregisters a client device token', function () {
    $client = ClientAccount::query()->create([
        'name' => 'عميل تجريبي',
        'email' => 'client-push@example.com',
        'phone' => '01000000000',
        'phone_normalized' => '01000000000',
        'password' => 'password',
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($client);

    $this->postJson('/api/client/device-token', [
        'token' => 'fcm-token-client-1',
        'platform' => 'android',
    ])->assertOk();

    expect(DeviceToken::query()->where('token', 'fcm-token-client-1')->value('client_account_id'))
        ->toBe($client->id);

    $this->deleteJson('/api/client/device-token', [
        'token' => 'fcm-token-client-1',
    ])->assertOk();

    expect(DeviceToken::query()->where('token', 'fcm-token-client-1')->exists())->toBeFalse();
});

it('rejects an unauthenticated device-token request', function () {
    $this->postJson('/api/device-token', [
        'token' => 'no-auth',
        'platform' => 'android',
    ])->assertUnauthorized();
});

it('rejects a token longer than 255 characters', function () {
    actingAsMerchant();

    $this->postJson('/api/device-token', [
        'token' => str_repeat('a', 256),
        'platform' => 'android',
    ])->assertUnprocessable();
});
