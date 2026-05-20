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
     * Get installments for a specific user with pagination and optional filters.
     *
     * @param  array{page?: int, per_page?: int, search?: string, status?: string, customer_id?: int}  $filters
     */
    public function getInstallmentsForUser(User $user, array $filters = []): LengthAwarePaginator;

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

    /**
     * Get installment statistics.
     */
    public function getInstallmentStats(int $installmentId, User $user): array;

    /**
     * Get all installments statistics summary.
     */
    public function getAllInstallmentsStats(User $user): array;

    /**
     * Send due-date reminders (in-app + email) for unpaid items on an installment.
     *
     * @return array{notifications_sent: int, emails_sent: int, items_reminded: int}
     */
    public function sendInstallmentDueReminders(int $installmentId, User $user, ?int $itemId = null): array;
}
