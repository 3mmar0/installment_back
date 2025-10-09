<?php

namespace App\Services;

use App\Contracts\Services\UserServiceInterface;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService implements UserServiceInterface
{
    /**
     * Get all users.
     */
    public function getAllUsers(): Collection
    {
        return User::latest()->get();
    }

    /**
     * Create a new user.
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'] ?? UserRole::User,
            ]);
        });
    }

    /**
     * Find a user by ID.
     */
    public function findUserById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Update a user.
     */
    public function updateUser(int $id, array $data): User
    {
        $user = User::findOrFail($id);

        return DB::transaction(function () use ($user, $data) {
            $updateData = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (isset($data['password']) && !empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            if (isset($data['role'])) {
                $updateData['role'] = $data['role'];
            }

            $user->update($updateData);
            return $user->fresh();
        });
    }

    /**
     * Delete a user.
     */
    public function deleteUser(int $id): bool
    {
        $user = User::findOrFail($id);

        return DB::transaction(function () use ($user) {
            // Check if user has any customers
            if ($user->customers()->count() > 0) {
                throw new \Exception('Cannot delete user with existing customers');
            }

            return $user->delete();
        });
    }

    /**
     * Get users for owner (only regular users).
     */
    public function getUsersForOwner()
    {
        return User::where('role', UserRole::User)
            ->latest()
            ->paginate(20);
    }

    /**
     * Get dashboard stats for a user.
     */
    public function getDashboardStats(User $user): array
    {
        $baseQuery = $user->installments();

        return [
            'total_installments' => $baseQuery->count(),
            'active_installments' => $baseQuery->where('status', 'active')->count(),
            'completed_installments' => $baseQuery->where('status', 'completed')->count(),
            'total_customers' => $user->customers()->count(),
        ];
    }
}
