<?php

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Mail::fake();
    Notification::fake();
});

it('assigns currency from the selected country on register', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Saudi Vendor',
        'email' => 'sa-vendor@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'country' => 'SA',
        'registration_source' => 'web',
    ])
        ->assertCreated()
        ->assertJsonPath('data.user.country', 'SA')
        ->assertJsonPath('data.user.currency', 'SAR');

    $user = User::where('email', 'sa-vendor@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->country)->toBe('SA')
        ->and($user->currency)->toBe('SAR');
});

it('requires a country on register', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Vendor',
        'email' => 'no-country@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('country');
});
