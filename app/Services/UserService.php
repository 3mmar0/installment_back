<?php

namespace App\Services;

use App\Contracts\Services\UserServiceInterface;
use App\Enums\RegistrationSource;
use App\Enums\UserRole;
use App\Helpers\TrialHelper;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Notification;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserService implements UserServiceInterface
{
    /**
     * Get all users.
     */
    public function getAllUsers(): Collection
    {
        return User::with('userLimit')->latest()->get();
    }

    /**
     * Create a new user.
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'] ?? UserRole::User,
                'registration_source' => RegistrationSource::Admin,
            ]);

            TrialHelper::applyRegistrationPlan($user, null);

            return $user->fresh('userLimit');
        });
    }

    /**
     * Find a user by ID.
     */
    public function findUserById(int $id): ?User
    {
        return User::with('userLimit')->find($id);
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

            if (isset($data['password']) && ! empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            if (isset($data['role'])) {
                $updateData['role'] = $data['role'];
            }

            $user->update($updateData);

            return $user->fresh('userLimit');
        });
    }

    /**
     * Delete a vendor/user and all owned business data.
     */
    public function deleteUser(int $id): bool
    {
        $user = User::findOrFail($id);

        if ($user->isOwner()) {
            throw ValidationException::withMessages([
                'user' => ['لا يمكن حذف حساب المدير'],
            ]);
        }

        $attachmentPaths = PaymentRequest::query()
            ->where('user_id', $user->id)
            ->pluck('attachment_path')
            ->filter()
            ->values()
            ->all();

        $deleted = DB::transaction(function () use ($user) {
            $this->purgeVendorOwnedData($user);
            $user->tokens()->delete();

            return (bool) $user->delete();
        });

        if ($deleted) {
            foreach ($attachmentPaths as $path) {
                Storage::disk('local')->delete($path);
            }
        }

        return $deleted;
    }

    /**
     * Remove customers, installments, notifications, and complaints for a vendor.
     * Related installment items and payment requests cascade via foreign keys.
     */
    private function purgeVendorOwnedData(User $user): void
    {
        Installment::query()->where('user_id', $user->id)->delete();
        Customer::query()->where('user_id', $user->id)->delete();
        Notification::query()->where('user_id', $user->id)->delete();
        Complaint::query()->where('user_id', $user->id)->delete();
        PaymentRequest::query()->where('user_id', $user->id)->delete();
    }

    /**
     * Get users for owner (only regular users).
     */
    public function getUsersForOwner()
    {
        return User::where('role', UserRole::User)
            ->with('userLimit')
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
