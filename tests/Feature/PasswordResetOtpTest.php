<?php

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

it('emails a 6-digit reset code and accepts it to change the password', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'reset-otp@example.com',
        'password' => Hash::make('OldPass123!'),
    ]);

    $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
        ->assertOk();

    $code = null;
    Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use ($user, &$code) {
        $code = $mail->code;

        return $mail->hasTo($user->email) && preg_match('/^\d{6}$/', $mail->code) === 1;
    });

    $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => $code,
        'password' => 'NewPass123!',
        'password_confirmation' => 'NewPass123!',
    ])->assertOk();

    expect(Hash::check('NewPass123!', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid 6-digit reset code', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
        ->assertOk();

    $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => '000000',
        'password' => 'NewPass123!',
        'password_confirmation' => 'NewPass123!',
    ])->assertStatus(422);
});

it('does not reveal whether an unknown email exists', function () {
    Mail::fake();

    $this->postJson('/api/auth/forgot-password', ['email' => 'missing@example.com'])
        ->assertOk();

    Mail::assertNothingSent();
});
