<?php

namespace App\Services;

use App\Contracts\Services\InstallmentServiceInterface;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InstallmentService implements InstallmentServiceInterface
{
    /**
     * Get installments for a specific user with pagination.
     */
    public function getInstallmentsForUser(User $user): LengthAwarePaginator
    {
        return Installment::query()
            ->with(['customer', 'items'])
            ->forUser($user)
            ->latest()
            ->paginate(20);
    }

    /**
     * Find an installment by ID.
     */
    public function findInstallmentById(int $id): ?Installment
    {
        return Installment::with(['customer', 'items'])->find($id);
    }

    /**
     * Create a new installment.
     */
    public function createInstallment(array $data, User $user): Installment
    {
        return DB::transaction(function () use ($data, $user) {
            $start = Carbon::parse($data['start_date'])->startOfDay();
            $months = (int) $data['months'];
            $total = round((float) $data['total_amount'], 2);
            $base = floor(($total / $months) * 100) / 100;
            $remainder = round($total - ($base * $months), 2);

            $installment = Installment::create([
                'user_id' => $user->id,
                'customer_id' => $data['customer_id'],
                'total_amount' => $total,
                'products' => $data['products'],
                'start_date' => $start->toDateString(),
                'months' => $months,
                'end_date' => $start->copy()->addMonths($months - 1)->toDateString(),
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
            ]);

            // Create installment items
            for ($i = 0; $i < $months; $i++) {
                $due = $start->copy()->addMonths($i);
                $amount = $base + ($i === ($months - 1) ? $remainder : 0.0);

                InstallmentItem::create([
                    'installment_id' => $installment->id,
                    'due_date' => $due->toDateString(),
                    'amount' => $amount,
                    'status' => 'pending',
                ]);
            }

            return $installment->load(['customer', 'items']);
        });
    }

    /**
     * Mark an installment item as paid.
     */
    public function markItemPaid(InstallmentItem $item, array $data, User $user): InstallmentItem
    {
        // Check authorization
        if (!$user->isOwner() && $item->installment->user_id !== $user->id) {
            abort(403, 'Unauthorized to update this installment item');
        }

        return DB::transaction(function () use ($item, $data) {
            $item->markPaid(
                (float) $data['paid_amount'],
                $data['reference'] ?? null
            );

            // Check if all items are paid
            $installment = $item->installment;
            $allPaid = $installment->items()->where('status', '!=', 'paid')->count() === 0;

            if ($allPaid) {
                $installment->update(['status' => 'completed']);
            }

            return $item->refresh();
        });
    }

    /**
     * Get dashboard analytics for a user.
     */
    public function getDashboardAnalytics(User $user): array
    {
        $baseQuery = Installment::query()->forUser($user);
        $now = now()->startOfDay();
        $soon = now()->addDays(7)->endOfDay();

        $dueSoon = $baseQuery->clone()
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->whereNull('installment_items.paid_at')
            ->whereBetween('installment_items.due_date', [$now, $soon])
            ->count('installment_items.id');

        $overdue = $baseQuery->clone()
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->whereNull('installment_items.paid_at')
            ->where('installment_items.due_date', '<', $now)
            ->count('installment_items.id');

        $outstanding = $baseQuery->clone()
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->whereNull('installment_items.paid_at')
            ->sum('installment_items.amount');

        $collectedThisMonth = $baseQuery->clone()
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->whereNotNull('installment_items.paid_at')
            ->whereMonth('installment_items.paid_at', $now->month)
            ->whereYear('installment_items.paid_at', $now->year)
            ->sum('installment_items.paid_amount');

        $upcoming = $baseQuery->clone()
            ->with(['customer:id,name,email'])
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->whereNull('installment_items.paid_at')
            ->whereBetween('installment_items.due_date', [$now, $soon])
            ->orderBy('installment_items.due_date')
            ->select('installments.*', 'installment_items.due_date', 'installment_items.amount')
            ->limit(10)
            ->get();

        return compact('dueSoon', 'overdue', 'outstanding', 'collectedThisMonth', 'upcoming');
    }

    /**
     * Get overdue items for a user.
     */
    public function getOverdueItems(User $user): Collection
    {
        return InstallmentItem::query()
            ->whereHas('installment', function ($query) use ($user) {
                $query->forUser($user);
            })
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->with(['installment.customer'])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Get due soon items for a user.
     */
    public function getDueSoonItems(User $user): Collection
    {
        return InstallmentItem::query()
            ->whereHas('installment', function ($query) use ($user) {
                $query->forUser($user);
            })
            ->where('status', 'pending')
            ->whereBetween('due_date', [now(), now()->addDays(7)])
            ->with(['installment.customer'])
            ->orderBy('due_date')
            ->get();
    }
}
