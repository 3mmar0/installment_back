<?php

namespace App\Services\Reports;

use App\Helpers\InstallmentDateHelper;
use App\Models\Installment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ReportQueryService
{
    /** @var list<string> */
    private const AGING_BUCKETS = ['1_7', '8_30', '31_60', '61_90', '90_plus'];

    /**
     * @param  array{page?: int, per_page?: int, days?: int}  $filters
     * @return array{summary: array<string, mixed>, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}}
     */
    public function run(User $user, string $key, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        return match ($key) {
            'overdue' => $this->overdue($user, $page, $perPage),
            'aging' => $this->aging($user, $page, $perPage),
            'due_soon' => $this->dueSoon($user, $page, $perPage, (int) ($filters['days'] ?? 14)),
            default => throw new InvalidArgumentException("Unknown report key: {$key}"),
        };
    }

    /**
     * @return array{summary: array<string, mixed>, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}}
     */
    private function overdue(User $user, int $page, int $perPage): array
    {
        $now = now()->startOfDay();
        $items = $this->unpaidItemsQuery($user)
            ->where('installment_items.due_date', '<', $now)
            ->orderBy('installment_items.due_date')
            ->get();

        $rows = $items->map(fn ($item) => [
            'item_id' => $item->item_id,
            'customer_name' => $item->customer_name,
            'installment_id' => $item->installment_id,
            'due_date' => Carbon::parse($item->due_date)->toDateString(),
            'days_overdue' => InstallmentDateHelper::daysOverdue($item->due_date),
            'amount' => round((float) $item->amount, 2),
        ]);

        return $this->paginate(
            $rows,
            [
                'count' => $rows->count(),
                'total_amount' => round((float) $rows->sum('amount'), 2),
            ],
            [
                ['key' => 'customer_name', 'label' => 'العميل'],
                ['key' => 'installment_id', 'label' => 'رقم القسط'],
                ['key' => 'due_date', 'label' => 'تاريخ الاستحقاق'],
                ['key' => 'days_overdue', 'label' => 'أيام التأخير'],
                ['key' => 'amount', 'label' => 'المبلغ'],
            ],
            $page,
            $perPage
        );
    }

    /**
     * @return array{summary: array<string, mixed>, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}}
     */
    private function aging(User $user, int $page, int $perPage): array
    {
        $now = now()->startOfDay();
        $items = $this->unpaidItemsQuery($user)
            ->where('installment_items.due_date', '<', $now)
            ->orderBy('installment_items.due_date')
            ->get();

        $buckets = array_fill_keys(self::AGING_BUCKETS, [
            'count' => 0,
            'total_amount' => 0.0,
        ]);

        $rows = $items->map(function ($item) use (&$buckets) {
            $days = InstallmentDateHelper::daysOverdue($item->due_date);
            $bucket = $this->agingBucket($days);
            $amount = round((float) $item->amount, 2);
            $buckets[$bucket]['count']++;
            $buckets[$bucket]['total_amount'] = round($buckets[$bucket]['total_amount'] + $amount, 2);

            return [
                'item_id' => $item->item_id,
                'customer_name' => $item->customer_name,
                'installment_id' => $item->installment_id,
                'due_date' => Carbon::parse($item->due_date)->toDateString(),
                'days_overdue' => $days,
                'bucket' => $bucket,
                'amount' => $amount,
            ];
        });

        return $this->paginate(
            $rows,
            ['buckets' => $buckets],
            [
                ['key' => 'customer_name', 'label' => 'العميل'],
                ['key' => 'installment_id', 'label' => 'رقم القسط'],
                ['key' => 'due_date', 'label' => 'تاريخ الاستحقاق'],
                ['key' => 'days_overdue', 'label' => 'أيام التأخير'],
                ['key' => 'bucket', 'label' => 'الفترة'],
                ['key' => 'amount', 'label' => 'المبلغ'],
            ],
            $page,
            $perPage
        );
    }

    /**
     * @return array{summary: array<string, mixed>, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}}
     */
    private function dueSoon(User $user, int $page, int $perPage, int $days): array
    {
        $days = max(1, min(90, $days));
        $now = now()->startOfDay();
        $until = now()->startOfDay()->addDays($days)->endOfDay();

        $items = $this->unpaidItemsQuery($user)
            ->whereBetween('installment_items.due_date', [$now, $until])
            ->orderBy('installment_items.due_date')
            ->get();

        $rows = $items->map(fn ($item) => [
            'item_id' => $item->item_id,
            'customer_name' => $item->customer_name,
            'installment_id' => $item->installment_id,
            'due_date' => Carbon::parse($item->due_date)->toDateString(),
            'days_until_due' => InstallmentDateHelper::daysUntilDue($item->due_date),
            'amount' => round((float) $item->amount, 2),
        ]);

        return $this->paginate(
            $rows,
            [
                'count' => $rows->count(),
                'total_amount' => round((float) $rows->sum('amount'), 2),
                'days' => $days,
            ],
            [
                ['key' => 'customer_name', 'label' => 'العميل'],
                ['key' => 'installment_id', 'label' => 'رقم القسط'],
                ['key' => 'due_date', 'label' => 'تاريخ الاستحقاق'],
                ['key' => 'days_until_due', 'label' => 'أيام حتى الاستحقاق'],
                ['key' => 'amount', 'label' => 'المبلغ'],
            ],
            $page,
            $perPage
        );
    }

    private function unpaidItemsQuery(User $user): Builder
    {
        return Installment::query()->forUser($user)
            ->join('installment_items', 'installment_items.installment_id', '=', 'installments.id')
            ->join('customers', 'customers.id', '=', 'installments.customer_id')
            ->whereNull('installment_items.paid_at')
            ->where('installment_items.status', '!=', 'paid')
            ->where('installments.status', 'active')
            ->select(
                'installments.id as installment_id',
                'installment_items.id as item_id',
                'installment_items.due_date',
                'installment_items.amount',
                'installment_items.status as item_status',
                'customers.name as customer_name'
            );
    }

    private function agingBucket(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 7 => '1_7',
            $daysOverdue <= 30 => '8_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => '90_plus',
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $summary
     * @param  list<array{key: string, label: string}>  $columns
     * @return array{summary: array<string, mixed>, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}}
     */
    private function paginate(Collection $rows, array $summary, array $columns, int $page, int $perPage): array
    {
        $total = $rows->count();
        $slice = $rows->forPage($page, $perPage)->values()->all();

        return [
            'summary' => $summary,
            'columns' => $columns,
            'rows' => $slice,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }
}
