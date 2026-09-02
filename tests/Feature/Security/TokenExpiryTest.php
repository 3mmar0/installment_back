<?php

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Each test issues exactly one authenticated request. The Sanctum guard instance
 * is reused for the lifetime of the test application and caches the resolved
 * user, so a second request would not re-evaluate the token's age.
 */
function agedToken(int $ageInMinutes): string
{
    $user = merchantWithPlan();
    $token = $user->createToken('test-device')->plainTextToken;

    SanctumToken::query()->update(['created_at' => now()->subMinutes($ageInMinutes)]);

    return $token;
}

it('leaves the token lifetime disabled by default', function () {
    // Off by default so that enabling it stays an explicit, gated deployment step.
    expect(config('sanctum.expiration'))->toBeNull();
});

it('rejects a token older than the configured lifetime', function () {
    config(['sanctum.expiration' => 60]);

    $token = agedToken(120);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/auth/me')
        ->assertUnauthorized();
});

it('accepts a token inside the configured lifetime', function () {
    config(['sanctum.expiration' => 60]);

    $token = agedToken(30);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/auth/me')
        ->assertOk();
});

it('accepts an old token while the lifetime is disabled', function () {
    $token = agedToken(60 * 24 * 365);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/auth/me')
        ->assertOk();
});
