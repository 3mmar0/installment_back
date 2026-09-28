<?php

namespace App\Services;

use App\Contracts\Services\CustomerServiceInterface;
use App\Helpers\LimitsHelper;
use App\Helpers\NationalIdHelper;
use App\Helpers\PhoneHelper;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Support\CustomerIdentity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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

        $query = ($user->canManageMerchantData() ? Customer::query() : $user->customers())
            ->with(['user', 'clientAccount:id,name,email,phone', 'currentCreditScore'])
            ->withCount('installments');

        if ($user->canManageMerchantData() && ! empty($filters['user_id'])) {
            $query->where('customers.user_id', (int) $filters['user_id']);
        }

        $hasClientAccount = (string) ($filters['has_client_account'] ?? '');
        if ($hasClientAccount === 'yes') {
            $query->whereNotNull('customers.client_account_id');
        } elseif ($hasClientAccount === 'no') {
            $query->whereNull('customers.client_account_id');
        }

        if ($hasInstallments === 'yes') {
            $query->has('installments');
        } elseif ($hasInstallments === 'no') {
            $query->doesntHave('installments');
        }

        $this->applyCreditScoreFilters($query, $filters);

        if ($search !== '') {
            $query->where(function ($builder) use ($search, $user) {
                if (ctype_digit($search)) {
                    $builder->where('customers.id', (int) $search);
                }

                $builder
                    ->orWhere('customers.name', 'like', "%{$search}%")
                    ->orWhere('customers.email', 'like', "%{$search}%")
                    ->orWhere('customers.phone', 'like', "%{$search}%")
                    ->orWhere('customers.national_id', 'like', "%{$search}%")
                    ->orWhere('customers.guarantor_name', 'like', "%{$search}%")
                    ->orWhere('customers.guarantor_phone', 'like', "%{$search}%")
                    ->orWhere('customers.address', 'like', "%{$search}%")
                    ->orWhere('customers.job', 'like', "%{$search}%");

                if ($user->canManageMerchantData()) {
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
            'score_desc' => $this->orderByCreditScore($query, 'desc'),
            'score_asc' => $this->orderByCreditScore($query, 'asc'),
            'score_change_desc' => $this->orderByCreditScoreChange($query, 'desc'),
            'score_change_asc' => $this->orderByCreditScoreChange($query, 'asc'),
            default => $query->latest('customers.id'),
        };

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return Collection<int, Customer>
     */
    public function getCustomersForSelect(User $user, ?string $search = null): Collection
    {
        $query = ($user->canManageMerchantData() ? Customer::query() : $user->customers())
            ->select(['customers.id', 'customers.name', 'customers.email', 'customers.phone']);

        $term = trim((string) $search);
        if ($term !== '') {
            $query->where(function ($builder) use ($term) {
                if (ctype_digit($term)) {
                    $builder->where('customers.id', (int) $term);
                }

                $builder
                    ->orWhere('customers.name', 'like', "%{$term}%")
                    ->orWhere('customers.email', 'like', "%{$term}%")
                    ->orWhere('customers.phone', 'like', "%{$term}%")
                    ->orWhere('customers.national_id', 'like', "%{$term}%");
            });
        }

        return $query->orderBy('customers.name')->limit(2000)->get();
    }

    /**
     * Find a customer by ID.
     */
    public function findCustomerById(int $id): ?Customer
    {
        return Customer::with(['user', 'clientAccount:id,name,email,phone'])->find($id);
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

            $nationalId = NationalIdHelper::normalize($data['national_id'] ?? null);
            $phone = $data['phone'] ?? null;

            CustomerIdentity::assertAvailable($user->id, $nationalId, $phone);

            $customer = Customer::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $phone,
                'phone_normalized' => PhoneHelper::normalize($phone),
                'national_id' => $nationalId,
                'address' => $data['address'] ?? null,
                'job' => $data['job'] ?? null,
                'monthly_salary' => array_key_exists('monthly_salary', $data)
                    ? $data['monthly_salary']
                    : null,
                'notes' => $data['notes'] ?? null,
                'guarantor_name' => $data['guarantor_name'] ?? null,
                'guarantor_national_id' => NationalIdHelper::normalize($data['guarantor_national_id'] ?? null),
                'guarantor_phone' => $data['guarantor_phone'] ?? null,
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

        if (array_key_exists('national_id', $data)) {
            $data['national_id'] = NationalIdHelper::normalize($data['national_id']);
        }
        if (array_key_exists('guarantor_national_id', $data)) {
            $data['guarantor_national_id'] = NationalIdHelper::normalize($data['guarantor_national_id']);
        }
        if (array_key_exists('phone', $data)) {
            $data['phone_normalized'] = PhoneHelper::normalize($data['phone']);
        }

        $nextNationalId = array_key_exists('national_id', $data)
            ? $data['national_id']
            : $customer->national_id;
        $nextPhone = array_key_exists('phone', $data)
            ? $data['phone']
            : $customer->phone;

        CustomerIdentity::assertAvailable(
            (int) $customer->user_id,
            $nextNationalId,
            $nextPhone,
            $customer->id
        );

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

        $stats = [
            'total_installments' => $installments->count(),
            'active_installments' => $installments->where('status', 'active')->count(),
            'total_amount' => $installments->sum('total_amount'),
            'paid_amount' => $installments->with('items')
                ->get()
                ->sum(function ($installment) {
                    return $installment->items->where('status', 'paid')->sum('paid_amount');
                }),
        ];

        $portalBreakdown = $this->getClientPortalInstallmentBreakdown($customer);
        if ($portalBreakdown !== null) {
            $stats['client_portal_breakdown'] = $portalBreakdown;
        }

        return $stats;
    }

    /**
     * Explain why a linked client may see more installments in the app than on this customer row.
     *
     * @return array<string, int>|null
     */
    public function getClientPortalInstallmentBreakdown(Customer $customer): ?array
    {
        if (! $customer->client_account_id) {
            return null;
        }

        $clientId = (int) $customer->client_account_id;
        $linkedCustomerIds = Customer::query()
            ->where('client_account_id', $clientId)
            ->pluck('id');

        $onThisCustomer = (int) $customer->installments()->count();

        $personal = (int) Installment::query()
            ->where('client_account_id', $clientId)
            ->whereNull('customer_id')
            ->count();

        $onOtherLinkedCustomers = (int) Installment::query()
            ->whereIn('customer_id', $linkedCustomerIds)
            ->where('customer_id', '!=', $customer->id)
            ->count();

        $totalInClientApp = $onThisCustomer + $personal + $onOtherLinkedCustomers;

        return [
            'total_in_client_app' => $totalInClientApp,
            'on_this_customer' => $onThisCustomer,
            'personal' => $personal,
            'on_other_linked_customers' => $onOtherLinkedCustomers,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Customer>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyCreditScoreFilters($query, array $filters): void
    {
        $needsJoin = false;
        foreach (['score_from', 'score_to', 'risk_level', 'confidence_level', 'score_trend', 'current_overdue', 'thin_file'] as $key) {
            if (! empty($filters[$key])) {
                $needsJoin = true;
                break;
            }
        }

        if ($needsJoin) {
            $query->leftJoin('customer_credit_scores as ccs', 'customers.current_credit_score_id', '=', 'ccs.id');
        }

        if (! empty($filters['score_from'])) {
            $query->where('ccs.score', '>=', (int) $filters['score_from']);
        }
        if (! empty($filters['score_to'])) {
            $query->where('ccs.score', '<=', (int) $filters['score_to']);
        }
        if (! empty($filters['risk_level'])) {
            $query->where('ccs.risk_level', (string) $filters['risk_level']);
        }
        if (! empty($filters['confidence_level'])) {
            $query->where('ccs.confidence_level', (string) $filters['confidence_level']);
        }
        if (($filters['score_trend'] ?? '') === 'improving') {
            $query->where('ccs.score_change', '>', 0);
        } elseif (($filters['score_trend'] ?? '') === 'declining') {
            $query->where('ccs.score_change', '<', 0);
        }
        if (($filters['current_overdue'] ?? '') === 'yes') {
            $query->where('ccs.current_overdue_count', '>', 0);
        } elseif (($filters['current_overdue'] ?? '') === 'no') {
            $query->where(function ($inner) {
                $inner->whereNull('ccs.id')->orWhere('ccs.current_overdue_count', '<=', 0);
            });
        }
        if (($filters['thin_file'] ?? '') === 'yes') {
            $query->where('ccs.thin_file', true);
        } elseif (($filters['thin_file'] ?? '') === 'no') {
            $query->where(function ($inner) {
                $inner->whereNull('ccs.id')->orWhere('ccs.thin_file', false);
            });
        }

        if ($needsJoin) {
            $query->select('customers.*');
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Customer>  $query
     */
    private function orderByCreditScore($query, string $direction): void
    {
        $query->leftJoin('customer_credit_scores as ccs_sort', 'customers.current_credit_score_id', '=', 'ccs_sort.id')
            ->select('customers.*')
            ->orderBy('ccs_sort.score', $direction);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Customer>  $query
     */
    private function orderByCreditScoreChange($query, string $direction): void
    {
        $query->leftJoin('customer_credit_scores as ccs_chg', 'customers.current_credit_score_id', '=', 'ccs_chg.id')
            ->select('customers.*')
            ->orderBy('ccs_chg.score_change', $direction);
    }
}
