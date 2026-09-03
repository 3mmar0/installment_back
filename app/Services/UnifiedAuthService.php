<?php

namespace App\Services;

use App\Contracts\Services\AuthServiceInterface;
use Illuminate\Validation\ValidationException;

class UnifiedAuthService
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly ClientAuthService $clientAuthService,
    ) {}

    /**
     * Resolve vendor vs client from credentials.
     *
     * @return array<string, mixed>
     */
    public function login(array $credentials): array
    {
        $vendorError = null;

        try {
            $vendor = $this->authService->login($credentials);

            return [
                'account_type' => 'vendor',
                ...$vendor,
            ];
        } catch (ValidationException $e) {
            $vendorError = $e;
        }

        try {
            $client = $this->clientAuthService->login($credentials);

            return [
                'account_type' => 'client',
                ...$client,
            ];
        } catch (ValidationException) {
            if ($vendorError) {
                throw $vendorError;
            }

            throw ValidationException::withMessages([
                'email' => ['بيانات الاعتماد غير صحيحة'],
            ]);
        }
    }
}
