<?php

namespace App\Services;

use App\Contracts\Services\CustomerServiceInterface;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerService implements CustomerServiceInterface
{
    /**
     * Get customers for a specific user with pagination.
     */
    public function getCustomersForUser(User $user): LengthAwarePaginator
    {
        if ($user->isOwner()) {
            return Customer::with('user')
                ->latest()
                ->paginate(20);
        }

        return $user->customers()
            ->latest()
            ->paginate(20);
    }

    /**
     * Find a customer by ID.
     */
    public function findCustomerById(int $id): ?Customer
    {
        return Customer::find($id);
    }

    /**
     * Create a new customer.
     */
    public function createCustomer(array $data, User $user): Customer
    {
        return DB::transaction(function () use ($data, $user) {
            $customer = Customer::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'notes' => $data['notes'],
            ]);

            return $customer;
        });
    }

    /**
     * Update a customer.
     */
    public function updateCustomer(int $id, array $data, User $user): Customer
    {
        $customer = Customer::findOrFail($id);

        // Check if user can update this customer
        if (!$user->isOwner() && $customer->user_id !== $user->id) {
            abort(403, 'Unauthorized to update this customer');
        }

        $customer->update($data);
        return $customer->fresh();
    }

    /**
     * Delete a customer.
     */
    public function deleteCustomer(int $id, User $user): bool
    {
        $customer = Customer::findOrFail($id);

        // Check if user can delete this customer
        if (!$user->isOwner() && $customer->user_id !== $user->id) {
            abort(403, 'Unauthorized to delete this customer');
        }

        return $customer->delete();
    }

    /**
     * Get customer statistics.
     */
    public function getCustomerStats(Customer $customer): array
    {
        $installments = $customer->installments();

        return [
            'total_installments' => $installments->count(),
            'active_installments' => $installments->where('status', 'active')->count(),
            'total_amount' => $installments->sum('total_amount'),
            'paid_amount' => $installments->with('items')
                ->get()
                ->sum(function ($installment) {
                    return $installment->items->where('status', 'paid')->sum('paid_amount');
                }),
        ];
    }
}
