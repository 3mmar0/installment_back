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

it('rejects forgot-password for an unknown email with a clear message', function () {
    Mail::fake();

    $this->postJson('/api/auth/forgot-password', ['email' => 'missing@example.com'])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath(
            'message',
            'هذا البريد الإلكتروني غير مسجل. تأكد من كتابته كما سجّلت به الحساب.'
        );

    Mail::assertNothingSent();
});

it('tells client accounts to use client login instead of vendor reset', function () {
    Mail::fake();

    App\Models\ClientAccount::create([
        'name' => 'Client',
        'email' => 'client-reset@example.com',
        'phone' => '01000000000',
        'phone_normalized' => '1000000000',
        'password' => 'Password123!',
    ]);

    $this->postJson('/api/auth/forgot-password', ['email' => 'client-reset@example.com'])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath(
            'message',
            'هذا البريد مسجل كحساب عميل. استخدم تسجيل دخول العميل وليس استعادة كلمة مرور البائع.'
        );

    Mail::assertNothingSent();
});
