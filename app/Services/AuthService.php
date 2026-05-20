<?php

namespace App\Services;

use App\Contracts\Services\AuthServiceInterface;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthService implements AuthServiceInterface
{
    /**
     * Authenticate user and generate token.
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Logout user and revoke tokens.
     */
    public function logout(User $user): bool
    {
        return $user->tokens()->delete();
    }

    /**
     * Register a new user.
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'] ?? 'user',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Refresh user token.
     */
    public function refreshToken(User $user): string
    {
        // Revoke all existing tokens
        $user->tokens()->delete();

        // Create new token
        return $user->createToken('api-token')->plainTextToken;
    }

    /**
     * Send password reset link to the user's email.
     */
    public function sendPasswordResetLink(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return;
        }

        $token = Password::broker()->createToken($user);
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $resetUrl = $frontendUrl . '/reset-password?' . http_build_query([
            'token' => $token,
            'email' => $email,
        ]);

        Mail::to($user->email)->send(new PasswordResetMail($user, $resetUrl, $token));
    }

    /**
     * Reset user password using token from email.
     */
    public function resetPassword(array $credentials): void
    {
        $status = Password::reset(
            [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
                'password_confirmation' => $credentials['password_confirmation'],
                'token' => $credentials['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return;
        }

        $message = match ($status) {
            Password::INVALID_TOKEN => 'رمز إعادة التعيين غير صالح أو منتهي الصلاحية',
            Password::INVALID_USER => 'البريد الإلكتروني غير مسجل',
            Password::RESET_THROTTLED => 'يرجى الانتظار قبل إعادة المحاولة',
            default => 'تعذر إعادة تعيين كلمة المرور',
        };

        throw ValidationException::withMessages([
            'token' => [$message],
        ]);
    }
}
