<?php

namespace App\Contracts\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserServiceInterface
{
    /**
     * Get all users.
     */
    public function getAllUsers(): Collection;

    /**
     * Create a new user.
     */
    public function createUser(array $data): User;

    /**
     * Find a user by ID.
     */
    public function findUserById(int $id): ?User;

    /**
     * Update a user.
     */
    public function updateUser(int $id, array $data): User;

    /**
     * Delete a user.
     */
    public function deleteUser(int $id): bool;
}
