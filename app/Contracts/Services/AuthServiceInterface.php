<?php

namespace App\Contracts\Services;

use App\Models\User;

interface AuthServiceInterface
{
    /**
     * Authenticate user and generate token.
     */
    public function login(array $credentials): array;

    /**
     * Logout user and revoke tokens.
     */
    public function logout(User $user): bool;

    /**
     * Register a new user.
     */
    public function register(array $data): array;

    /**
     * Refresh user token.
     */
    public function refreshToken(User $user): string;
}
