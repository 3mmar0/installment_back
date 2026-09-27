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
     * Send a 6-digit password reset code to the user's email.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function sendPasswordResetLink(string $email): void;

    /**
     * Reset user password using the 6-digit code from email.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function resetPassword(array $credentials): void;

    /**
     * Permanently delete the authenticated user's account.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function deleteAccount(User $user, string $password): void;
}
