<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('records last_active_at when a user logs in', function () {
    $user = merchantWithPlan();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    expect($user->fresh()->last_active_at)->not->toBeNull();
});

it('records last_active_at on authenticated api use', function () {
    $user = actingAsMerchant();

    expect($user->last_active_at)->toBeNull();

    $this->getJson('/api/auth/me')->assertOk();

    expect($user->fresh()->last_active_at)->not->toBeNull();
});

it('hides activity fields from regular users', function () {
    actingAsMerchant();

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonMissingPath('data.last_active_at')
        ->assertJsonMissingPath('data.is_active');
});

it('shows last_active_at and is_active to owners on the user list', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $activeUser = merchantWithPlan();
    $inactiveUser = merchantWithPlan();

    $activeUser->forceFill(['last_active_at' => now()->subDay()])->save();
    $inactiveUser->forceFill(['last_active_at' => now()->subDays(20)])->save();

    Sanctum::actingAs($owner);

    $payload = $this->getJson('/api/user-list')->assertOk()->json();
    $rows = collect($payload['data']['data'] ?? $payload['data'] ?? []);
    $activeRow = $rows->firstWhere('id', $activeUser->id);
    $inactiveRow = $rows->firstWhere('id', $inactiveUser->id);

    expect($activeRow)->not->toBeNull()
        ->and($activeRow['is_active'])->toBeTrue()
        ->and($activeRow['last_active_at'])->not->toBeNull()
        ->and($inactiveRow['is_active'])->toBeFalse()
        ->and($inactiveRow['last_active_at'])->not->toBeNull();
});
