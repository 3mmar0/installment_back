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
        $thisMonth = $now->copy()->startOfMonth();
        $lastMonth = $now->copy()->subMonth()->startOfMonth();

        // Basic counts and amounts
        $dueSoon = $baseQuery->clone()
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->whereNull('installment_items.paid_at')
            ->where('installment_items.status', '!=', 'paid')
            ->where('installments.status', 'active')
            ->whereBetween('installment_items.due_date', [$now, $soon])
            ->count('installment_items.id');

        $overdue = $baseQuery->clone()
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->whereNull('installment_items.paid_at')
            ->where('installment_items.status', '!=', 'paid')
            ->where('installments.status', 'active')
            ->where('installment_items.due_date', '<', $now)
            ->count('installment_items.id');

        $outstanding = $baseQuery->clone()
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->whereNull('installment_items.paid_at')
            ->where('installments.status', 'active')
            ->sum('installment_items.amount');

        $collectedThisMonth = $baseQuery->clone()
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->whereNotNull('installment_items.paid_at')
            ->where('installments.status', 'active')
            ->whereMonth('installment_items.paid_at', $now->month)
            ->whereYear('installment_items.paid_at', $now->year)
            ->sum('installment_items.paid_amount');

        // Additional analytics
        $totalInstallments = $baseQuery->clone()->count();
        $activeInstallments = $baseQuery->clone()->where('installments.status', 'active')->count();
        $completedInstallments = $baseQuery->clone()->where('installments.status', 'completed')->count();

        $totalCustomers = $user->customers()->count();
        $activeCustomers = $user->customers()
            ->whereHas('installments', function ($query) {
                $query->where('installments.status', 'active');
            })->count();

        // Monthly collection comparison
        $collectedLastMonth = $baseQuery->clone()
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->whereNotNull('installment_items.paid_at')
            ->where('installments.status', 'active')
            ->whereMonth('installment_items.paid_at', $lastMonth->month)
            ->whereYear('installment_items.paid_at', $lastMonth->year)
            ->sum('installment_items.paid_amount');

        $collectionGrowth = $lastMonth->isSameMonth($now) ? 0 : ($collectedLastMonth > 0 ? (($collectedThisMonth - $collectedLastMonth) / $collectedLastMonth) * 100 : 0);

        // Detailed upcoming payments table
        $upcoming = $baseQuery->clone()
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->join('customers', 'customers.id', '=', 'installments.customer_id')
            ->whereNull('installment_items.paid_at')
            ->where('installment_items.status', '!=', 'paid')
            ->where('installments.status', 'active')
            ->whereBetween('installment_items.due_date', [$now, $soon])
            ->orderBy('installment_items.due_date')
            ->select(
                'installments.id as installment_id',
                'installments.total_amount',
                'installments.months',
                'installments.status',
                'installments.created_at',
                'installment_items.id as item_id',
                'installment_items.due_date',
                'installment_items.amount',
                'installment_items.status as item_status',
                'customers.name as customer_name',
                'customers.email as customer_email',
                'customers.phone as customer_phone'
            )
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'installment_id' => $item->installment_id,
                    'customer_name' => $item->customer_name,
                    'customer_email' => $item->customer_email,
                    'customer_phone' => $item->customer_phone,
                    'total_amount' => $item->total_amount,
                    'months' => $item->months,
                    'due_date' => $item->due_date,
                    'amount' => $item->amount,
                    'status' => $item->status,
                    'item_status' => $item->item_status,
                    'created_at' => $item->created_at,
                    'days_until_due' => now()->diffInDays($item->due_date, false),
                ];
            });

        // Overdue payments table
        $overduePayments = $baseQuery->clone()
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->join('customers', 'customers.id', '=', 'installments.customer_id')
            ->whereNull('installment_items.paid_at')
            ->where('installment_items.status', '!=', 'paid')
            ->where('installments.status', 'active')
            ->where('installment_items.due_date', '<', $now)
            ->orderBy('installment_items.due_date')
            ->select(
                'installments.id as installment_id',
                'installments.total_amount',
                'installments.months',
                'installments.status',
                'installments.created_at',
                'installment_items.id as item_id',
                'installment_items.due_date',
                'installment_items.amount',
                'installment_items.status as item_status',
                'customers.name as customer_name',
                'customers.email as customer_email',
                'customers.phone as customer_phone'
            )
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'installment_id' => $item->installment_id,
                    'customer_name' => $item->customer_name,
                    'customer_email' => $item->customer_email,
                    'customer_phone' => $item->customer_phone,
                    'total_amount' => $item->total_amount,
                    'months' => $item->months,
                    'due_date' => $item->due_date,
                    'amount' => $item->amount,
                    'status' => $item->status,
                    'item_status' => $item->item_status,
                    'created_at' => $item->created_at,
                    'days_overdue' => now()->diffInDays($item->due_date),
                ];
            });

        // Recent payments table
        $recentPayments = $baseQuery->clone()
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->join('customers', 'customers.id', '=', 'installments.customer_id')
            ->whereNotNull('installment_items.paid_at')
            ->where('installments.status', 'active')
            ->orderBy('installment_items.paid_at', 'desc')
            ->select(
                'installments.id as installment_id',
                'installments.total_amount',
                'installment_items.id as item_id',
                'installment_items.due_date',
                'installment_items.amount',
                'installment_items.paid_amount',
                'installment_items.paid_at',
                'installment_items.reference',
                'customers.name as customer_name',
                'customers.email as customer_email'
            )
            ->limit(15)
            ->get()
            ->map(function ($item) {
                return [
                    'installment_id' => $item->installment_id,
                    'customer_name' => $item->customer_name,
                    'customer_email' => $item->customer_email,
                    'due_date' => $item->due_date,
                    'amount' => $item->amount,
                    'paid_amount' => $item->paid_amount,
                    'paid_at' => $item->paid_at,
                    'reference' => $item->reference,
                    'days_since_paid' => now()->diffInDays($item->paid_at),
                ];
            });

        // Top customers by outstanding amount
        $topCustomers = $user->customers()
            ->withCount(['installments' => function ($query) {
                $query->where('installments.status', 'active');
            }])
            ->withSum(['installments as total_outstanding' => function ($query) {
                $query->where('installments.status', 'active')
                    ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
                    ->whereNull('installment_items.paid_at')
                    ->where('installment_items.status', '!=', 'paid');
            }], 'installment_items.amount')
            ->having('total_outstanding', '>', 0)
            ->orderBy('total_outstanding', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'active_installments' => $customer->installments_count,
                    'total_outstanding' => $customer->total_outstanding ?? 0,
                ];
            });

        // Monthly collection trend (last 6 months)
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $monthlyCollection = $baseQuery->clone()
                ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
                ->whereNotNull('installment_items.paid_at')
                ->where('installments.status', 'active')
                ->whereMonth('installment_items.paid_at', $month->month)
                ->whereYear('installment_items.paid_at', $month->year)
                ->sum('installment_items.paid_amount');

            $monthlyTrend[] = [
                'month' => $month->format('M Y'),
                'amount' => $monthlyCollection,
                'year' => $month->year,
                'month_number' => $month->month,
            ];
        }

        return [
            // Summary stats
            'dueSoon' => $dueSoon,
            'overdue' => $overdue,
            'outstanding' => $outstanding,
            'collectedThisMonth' => $collectedThisMonth,
            'totalInstallments' => $totalInstallments,
            'activeInstallments' => $activeInstallments,
            'completedInstallments' => $completedInstallments,
            'totalCustomers' => $totalCustomers,
            'activeCustomers' => $activeCustomers,
            'collectedLastMonth' => $collectedLastMonth,
            'collectionGrowth' => round($collectionGrowth, 2),

            // Detailed tables
            'upcoming' => $upcoming,
            'overduePayments' => $overduePayments,
            'recentPayments' => $recentPayments,
            'topCustomers' => $topCustomers,
            'monthlyTrend' => $monthlyTrend,
        ];
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
