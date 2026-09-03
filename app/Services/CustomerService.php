<?php

namespace App\Services;

use App\Contracts\Services\CustomerServiceInterface;
use App\Helpers\LimitsHelper;
use App\Helpers\PhoneHelper;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CustomerService implements CustomerServiceInterface
{
    public function __construct(
        private readonly ClientLinkService $clientLinkService
    ) {}

    /**
     * Get customers for a specific user with pagination and optional search.
     *
     * @param  array{page?: int, per_page?: int, search?: string, user_id?: int, has_installments?: string, sort?: string}  $filters
     */
    public function getCustomersForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $search = trim((string) ($filters['search'] ?? ''));
        $hasInstallments = (string) ($filters['has_installments'] ?? '');
        $sort = (string) ($filters['sort'] ?? 'newest');

        $query = ($user->isOwner() ? Customer::query() : $user->customers())
            ->with('user')
            ->withCount('installments');

        if ($user->isOwner() && ! empty($filters['user_id'])) {
            $query->where('customers.user_id', (int) $filters['user_id']);
        }

        if ($hasInstallments === 'yes') {
            $query->has('installments');
        } elseif ($hasInstallments === 'no') {
            $query->doesntHave('installments');
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search, $user) {
                if (ctype_digit($search)) {
                    $builder->where('customers.id', (int) $search);
                }

                $builder
                    ->orWhere('customers.name', 'like', "%{$search}%")
                    ->orWhere('customers.email', 'like', "%{$search}%")
                    ->orWhere('customers.phone', 'like', "%{$search}%")
                    ->orWhere('customers.address', 'like', "%{$search}%");

                if ($user->isOwner()) {
                    $builder->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                }
            });
        }

        match ($sort) {
            'oldest' => $query->oldest('customers.id'),
            'name_asc' => $query->orderBy('customers.name'),
            'name_desc' => $query->orderByDesc('customers.name'),
            default => $query->latest('customers.id'),
        };

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Find a customer by ID.
     */
    public function findCustomerById(int $id): ?Customer
    {
        return Customer::with('user')->find($id);
    }

    /**
     * Create a new customer.
     */
    public function createCustomer(array $data, User $user): Customer
    {
        return DB::transaction(function () use ($data, $user) {
            if (! $user->isOwner() && ! LimitsHelper::canCreate($user->id, 'customers')) {
                abort(403, LimitsHelper::getLimitExceededMessage('customers'));
            }

            $customer = Customer::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'phone_normalized' => PhoneHelper::normalize($data['phone'] ?? null),
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if (! $user->isOwner()) {
                LimitsHelper::incrementUsage($user->id, 'customers');
            }

            $this->clientLinkService->linkForCustomer($customer);

            return $customer->fresh();
        });
    }

    /**
     * Update a customer.
     */
    public function updateCustomer(int $id, array $data, User $user): Customer
    {
        $customer = Customer::findOrFail($id);

        if (array_key_exists('phone', $data)) {
            $data['phone_normalized'] = PhoneHelper::normalize($data['phone']);
        }

        $customer->update($data);
        $customer = $customer->fresh();

        $this->clientLinkService->linkForCustomer($customer);

        return $customer;
    }

    public function deleteCustomer(int $id, User $user): bool
    {
        $customer = Customer::findOrFail($id);
        $owner = $customer->user;
        $installmentIds = $customer->installments()->pluck('id');
        $installmentCount = $installmentIds->count();

        $attachmentPaths = PaymentRequest::query()
            ->whereIn('installment_id', $installmentIds)
            ->pluck('attachment_path')
            ->filter()
            ->values()
            ->all();

        $deleted = DB::transaction(function () use ($customer, $installmentIds) {
            if ($installmentIds->isNotEmpty()) {
                Installment::query()->whereIn('id', $installmentIds)->delete();
            }

            return (bool) $customer->delete();
        });

        if ($deleted) {
            foreach ($attachmentPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            if ($owner && ! $owner->isOwner()) {
                LimitsHelper::decrementUsage($customer->user_id, 'customers');
                if ($installmentCount > 0) {
                    LimitsHelper::decrementUsage(
                        $customer->user_id,
                        'installments',
                        $installmentCount
                    );
                }
            }
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
