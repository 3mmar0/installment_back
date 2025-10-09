<?php

namespace App\Contracts\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerServiceInterface
{
    /**
     * Get customers for a specific user with pagination.
     */
    public function getCustomersForUser(User $user): LengthAwarePaginator;

    /**
     * Create a new customer.
     */
    public function createCustomer(array $data, User $user): Customer;

    /**
     * Find a customer by ID.
     */
    public function findCustomerById(int $id): ?Customer;

    /**
     * Update a customer.
     */
    public function updateCustomer(int $id, array $data, User $user): Customer;

    /**
     * Delete a customer.
     */
    public function deleteCustomer(int $id, User $user): bool;

    /**
     * Get customer statistics.
     */
    public function getCustomerStats(Customer $customer): array;
}
