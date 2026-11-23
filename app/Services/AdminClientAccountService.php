<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\ClientAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminClientAccountService
{
    /**
     * @param  array{page?: int, per_page?: int, search?: string|null, verified?: string|null, linked?: string|null}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $search = trim((string) ($filters['search'] ?? ''));
        $verified = (string) ($filters['verified'] ?? '');
        $linked = (string) ($filters['linked'] ?? '');

        $query = ClientAccount::query()
            ->withCount('customers')
            ->withCount('personalInstallments')
            ->orderByDesc('id');

        if ($search !== '') {
            $normalizedPhone = PhoneHelper::normalize($search);

            $query->where(function ($builder) use ($search, $normalizedPhone) {
                $builder->where('email', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');

                if ($normalizedPhone) {
                    $builder->orWhere('phone_normalized', $normalizedPhone);
                }
            });
        }

        if ($verified === 'yes') {
            $query->whereNotNull('email_verified_at');
        } elseif ($verified === 'no') {
            $query->whereNull('email_verified_at');
        }

        if ($linked === 'yes') {
            $query->has('customers');
        } elseif ($linked === 'no') {
            $query->doesntHave('customers');
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): ClientAccount
    {
        return ClientAccount::query()
            ->withCount('customers')
            ->withCount('personalInstallments')
            ->with([
                'customers' => function ($query) {
                    $query->with(['user:id,name,email'])
                        ->orderByDesc('id');
                },
            ])
            ->findOrFail($id);
    }

    public function delete(int $id): bool
    {
        $client = ClientAccount::query()->findOrFail($id);

        return DB::transaction(function () use ($client) {
            $client->tokens()->delete();

            return (bool) $client->delete();
        });
    }
}
