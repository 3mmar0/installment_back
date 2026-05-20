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

    /**
     * Send password reset link to the user's email.
     */
    public function sendPasswordResetLink(string $email): void;

    /**
     * Reset user password using token from email.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function resetPassword(array $credentials): void;
}
