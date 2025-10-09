<?php

namespace App\Contracts\Services;

use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InstallmentServiceInterface
{
    /**
     * Get installments for a specific user with pagination.
     */
    public function getInstallmentsForUser(User $user): LengthAwarePaginator;

    /**
     * Create a new installment.
     */
    public function createInstallment(array $data, User $user): Installment;

    /**
     * Find an installment by ID.
     */
    public function findInstallmentById(int $id): ?Installment;

    /**
     * Mark an installment item as paid.
     */
    public function markItemPaid(InstallmentItem $item, array $data, User $user): InstallmentItem;

    /**
     * Get dashboard analytics for a user.
     */
    public function getDashboardAnalytics(User $user): array;

    /**
     * Get overdue items for a user.
     */
    public function getOverdueItems(User $user): Collection;

    /**
     * Get due soon items for a user.
     */
    public function getDueSoonItems(User $user): Collection;
}
