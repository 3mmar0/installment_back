<?php

namespace App\Services;

use App\Exceptions\MailDeliveryException;
use App\Helpers\PhoneHelper;
use App\Mail\ClientEmailOtpMail;
use App\Models\ClientAccount;
use App\Models\ClientEmailVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClientAuthService
{
    private const OTP_TTL_MINUTES = 10;

    private const OTP_MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly ClientLinkService $clientLinkService
    ) {}

    /**
     * Register a new client account (unverified). Does not issue a token.
     *
     * @return array{client: ClientAccount}
     */
    public function register(array $data): array
    {
        $phoneNormalized = PhoneHelper::normalize($data['phone']);

        if ($phoneNormalized === null) {
            throw ValidationException::withMessages([
                'phone' => ['رقم الهاتف غير صالح'],
            ]);
        }

        $client = ClientAccount::create([
            'name' => $data['name'] ?? null,
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'],
            'phone_normalized' => $phoneNormalized,
            'password' => $data['password'],
            'email_verified_at' => null,
        ]);

        $this->sendOtp($client);

        return ['client' => $client];
    }

    /**
     * Send (or resend) a 5-digit OTP to the client's email.
     */
    public function sendOtp(ClientAccount $client): void
    {
        // Invalidate prior unconsumed codes.
        $client->emailVerifications()
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = (string) random_int(10000, 99999);

        ClientEmailVerification::create([
            'client_account_id' => $client->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'attempts' => 0,
        ]);

        try {
            Mail::to($client->email)->send(new ClientEmailOtpMail($client, $code));
        } catch (Throwable $e) {
            Log::error('Client OTP email failed', [
                'client_account_id' => $client->id,
                'email' => $client->email,
                'error' => $e->getMessage(),
            ]);

            throw new MailDeliveryException(previous: $e);
        }
    }

    /**
     * Verify OTP, mark email verified, link customers, issue token.
     *
     * @return array{client: ClientAccount, token: string, token_type: string, linked_customers: int}
     */
    public function verifyOtp(string $email, string $code): array
    {
        $client = ClientAccount::where('email', strtolower(trim($email)))->first();

        if (! $client) {
            throw ValidationException::withMessages([
                'email' => ['البريد الإلكتروني غير مسجل'],
            ]);
        }

        if ($client->isEmailVerified()) {
            throw ValidationException::withMessages([
                'email' => ['البريد الإلكتروني مؤكد مسبقاً'],
            ]);
        }

        $verification = $client->emailVerifications()
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $verification || $verification->isExpired()) {
            throw ValidationException::withMessages([
                'code' => ['رمز التحقق منتهي أو غير موجود، يرجى طلب رمز جديد'],
            ]);
        }

        if ($verification->attempts >= self::OTP_MAX_ATTEMPTS) {
            $verification->update(['consumed_at' => now()]);

            throw ValidationException::withMessages([
                'code' => ['تم تجاوز عدد المحاولات، يرجى طلب رمز جديد'],
            ]);
        }

        if (! Hash::check($code, $verification->code_hash)) {
            $verification->increment('attempts');

            throw ValidationException::withMessages([
                'code' => ['رمز التحقق غير صحيح'],
            ]);
        }

        return DB::transaction(function () use ($client, $verification) {
            $verification->update(['consumed_at' => now()]);
            $client->forceFill(['email_verified_at' => now()])->save();

            $linked = $this->clientLinkService->linkForClient($client);

            $token = $client->createToken('client-api-token')->plainTextToken;
            $client->markAsActive(0);

            return [
                'client' => $client->fresh(),
                'token' => $token,
                'token_type' => 'Bearer',
                'linked_customers' => $linked,
            ];
        });
    }

    /**
     * Login. If unverified, resends OTP and returns requires_verification.
     *
     * @return array{requires_verification?: bool, client: ClientAccount, token?: string, token_type?: string}
     */
    public function login(array $credentials): array
    {
        $client = ClientAccount::where('email', strtolower(trim($credentials['email'])))->first();

        if (! $client || ! Hash::check($credentials['password'], $client->password)) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الاعتماد غير صحيحة'],
            ]);
        }

        if (! $client->isEmailVerified()) {
            $this->sendOtp($client);

            return [
                'requires_verification' => true,
                'client' => $client,
            ];
        }

        // Re-link in case vendors added customers after last login.
        $this->clientLinkService->linkForClient($client);

        $token = $client->createToken('client-api-token')->plainTextToken;
        $client->markAsActive(0);

        return [
            'client' => $client,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    public function logout(ClientAccount $client): bool
    {
        return (bool) $client->tokens()->delete();
    }

    public function refreshToken(ClientAccount $client): string
    {
        $client->tokens()->delete();

        return $client->createToken('client-api-token')->plainTextToken;
    }

    public function findByEmail(string $email): ?ClientAccount
    {
        return ClientAccount::where('email', strtolower(trim($email)))->first();
    }
}
