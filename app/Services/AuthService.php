<?php

namespace App\Services;

use App\Contracts\Services\AuthServiceInterface;
use App\Contracts\Services\UserServiceInterface;
use App\Enums\RegistrationSource;
use App\Enums\UserRole;
use App\Helpers\LocaleHelper;
use App\Exceptions\MailDeliveryException;
use App\Mail\PasswordResetMail;
use App\Models\ClientAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private readonly UserServiceInterface $userService
    ) {}

    /**
     * Authenticate user and generate token.
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;
        $user->markAsActive(0);

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
        $country = strtoupper(trim((string) ($data['country'] ?? 'EG')));

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'country' => $country,
            'currency' => LocaleHelper::currencyForCountry($country),
            'password' => Hash::make($data['password']),
            'role' => $data['role'] ?? UserRole::User,
            'registration_source' => RegistrationSource::tryFrom($data['registration_source'] ?? 'web')
                ?? RegistrationSource::Web,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;
        $user->markAsActive(0);

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
     * Send a 6-digit password reset code to the user's email.
     */
    public function sendPasswordResetLink(string $email): void
    {
        $normalized = strtolower(trim($email));
        $user = User::whereRaw('LOWER(email) = ?', [$normalized])->first();

        if (! $user) {
            $isClient = ClientAccount::whereRaw('LOWER(email) = ?', [$normalized])->exists();

            throw ValidationException::withMessages([
                'email' => [
                    $isClient
                        ? 'هذا البريد مسجل كحساب عميل. استخدم تسجيل دخول العميل وليس استعادة كلمة مرور البائع.'
                        : 'هذا البريد الإلكتروني غير مسجل. تأكد من كتابته كما سجّلت به الحساب.',
                ],
            ]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $table = config('auth.passwords.users.table', 'password_reset_tokens');

        DB::table($table)->updateOrInsert(
            ['email' => $user->getEmailForPasswordReset()],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        try {
            Mail::to($user->email)->send(new PasswordResetMail($user, $code));
        } catch (Throwable $e) {
            Log::error('Password reset email failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw new MailDeliveryException(previous: $e);
        }
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
                    'password' => $password,
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

    /**
     * Permanently delete the authenticated user's account.
     */
    public function deleteAccount(User $user, string $password): void
    {
        if ($user->role === UserRole::Owner) {
            throw ValidationException::withMessages([
                'account' => ['لا يمكن حذف حساب المدير العام'],
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['كلمة المرور غير صحيحة'],
            ]);
        }

        $this->userService->deleteUser($user->id);
    }
}
