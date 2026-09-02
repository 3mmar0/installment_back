<?php

namespace App\Services;

use App\Contracts\Services\CustomerServiceInterface;
use App\Helpers\LimitsHelper;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerService implements CustomerServiceInterface
{
    /**
     * Get customers for a specific user with pagination and optional search.
     *
     * @param  array{page?: int, per_page?: int, search?: string}  $filters
     */
    public function getCustomersForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = $user->isOwner()
            ? Customer::query()->with('user')
            : $user->customers();

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                if (ctype_digit($search)) {
                    $builder->where('customers.id', (int) $search);
                }

                $builder
                    ->orWhere('customers.name', 'like', "%{$search}%")
                    ->orWhere('customers.email', 'like', "%{$search}%")
                    ->orWhere('customers.phone', 'like', "%{$search}%")
                    ->orWhere('customers.address', 'like', "%{$search}%");
            });
        }

        return $query->latest('customers.id')->paginate($perPage, ['*'], 'page', $page);
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
            if (!$user->isOwner() && !LimitsHelper::canCreate($user->id, 'customers')) {
                abort(403, LimitsHelper::getLimitExceededMessage('customers'));
            }

            $customer = Customer::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if (!$user->isOwner()) {
                LimitsHelper::incrementUsage($user->id, 'customers');
            }

            return $customer;
        });
    }

    /**
     * Update a customer.
     */
    public function updateCustomer(int $id, array $data, User $user): Customer
    {
        $customer = Customer::findOrFail($id);
        $customer->update($data);

        return $customer->fresh();
    }

    public function deleteCustomer(int $id, User $user): bool
    {
        $customer = Customer::findOrFail($id);
        $owner = $customer->user;

        $deleted = DB::transaction(function () use ($customer) {
            return $customer->delete();
        });

        if ($deleted && $owner && !$owner->isOwner()) {
            LimitsHelper::decrementUsage($customer->user_id, 'customers');
        }

        return $deleted;
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
