<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->get('/api/__boom', function () {
        throw new RuntimeException('exploding for the test');
    });
});

it('never returns a stack trace even with debug enabled', function () {
    config(['app.debug' => true]);

    $response = $this->getJson('/api/__boom')->assertStatus(500);

    expect($response->json())->not->toHaveKey('trace')
        ->and($response->json('error'))->not->toHaveKey('trace');
});

it('hides the exception message when debug is disabled', function () {
    config(['app.debug' => false]);

    $response = $this->getJson('/api/__boom')->assertStatus(500);

    expect($response->json('message'))->not->toContain('exploding for the test')
        ->and($response->json('error'))->toBeNull();
});

it('does not grant platform admin rights by default', function () {
    expect(config('app.platform_admin_emails'))->toBe([]);
});
