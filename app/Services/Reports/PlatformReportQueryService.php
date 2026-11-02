<?php

namespace App\Services\Reports;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\User;
use App\Support\Engagement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PlatformReportQueryService
{
    /**
     * @param  array{page?: int, per_page?: int, days?: int, search?: string, min_usage?: int}  $filters
     * @return array{summary: array<string, mixed>, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}}
     */
    public function run(string $key, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        return match ($key) {
            'platform_overview' => $this->overview(),
            'platform_active_merchants' => $this->activeMerchants(
                $page,
                $perPage,
                (int) ($filters['days'] ?? 30),
                $filters['search'] ?? null
            ),
            'platform_inactive_candidates' => $this->inactiveCandidates(
                $page,
                $perPage,
                $filters['search'] ?? null
            ),
            'platform_resource_usage' => $this->resourceUsage(
                $page,
                $perPage,
                (int) ($filters['min_usage'] ?? 0),
                $filters['search'] ?? null
            ),
            'platform_subscriptions' => $this->subscriptions(),
            default => throw new InvalidArgumentException("Unknown platform report key: {$key}"),
        };
    }

    /**
     * @return array{summary: array<string, mixed>, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}}
     */
    private function overview(): array
    {
        $merchants = $this->merchantQuery()->with('userLimit')->get();
        $now = now();
        $active7 = 0;
        $active30 = 0;
        $inactive5m = 0;
        $trials = 0;
        $customersUsed = 0;
        $installmentsUsed = 0;

        foreach ($merchants as $merchant) {
            $last = $merchant->last_active_at;
            if ($last && $last->gte($now->copy()->subDays(7))) {
                $active7++;
            }
            if ($last && $last->gte($now->copy()->subDays(30))) {
                $active30++;
            }
            if (! Engagement::isActive($last, $merchant->created_at)) {
                $inactive5m++;
            }
            $limit = $merchant->userLimit;
            if ($limit && str_contains(strtolower((string) ($limit->subscription_slug ?? '')), 'trial')) {
                $trials++;
            } elseif ($merchant->trial_used_at && (! $limit || $limit->status !== 'active')) {
                $trials++;
            }
            $customersUsed += (int) ($limit->customers_used ?? 0);
            $installmentsUsed += (int) ($limit->installments_used ?? 0);
        }

        $summary = [
            'merchants_total' => $merchants->count(),
            'active_7d' => $active7,
            'active_30d' => $active30,
            'inactive_5m' => $inactive5m,
            'trial_like' => $trials,
            'customers_used_total' => $customersUsed,
            'installments_used_total' => $installmentsUsed,
            'customers_db_total' => Customer::query()->count(),
            'installments_db_total' => Installment::query()->count(),
        ];

        return [
            'summary' => $summary,
            'columns' => [
                ['key' => 'metric', 'label' => 'المؤشر'],
                ['key' => 'value', 'label' => 'القيمة'],
            ],
            'rows' => [
                ['metric' => 'إجمالي التجار', 'value' => $summary['merchants_total']],
                ['metric' => 'نشط خلال 7 أيام', 'value' => $summary['active_7d']],
                ['metric' => 'نشط خلال 30 يوم', 'value' => $summary['active_30d']],
                ['metric' => 'خامل (+5 شهور)', 'value' => $summary['inactive_5m']],
                ['metric' => 'استخدام العملاء (limits)', 'value' => $summary['customers_used_total']],
                ['metric' => 'استخدام الأقساط (limits)', 'value' => $summary['installments_used_total']],
            ],
            'meta' => [
                'total' => 6,
                'page' => 1,
                'per_page' => 6,
            ],
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}}
     */
    private function activeMerchants(int $page, int $perPage, int $days, ?string $search): array
    {
        $days = max(1, min(90, $days));
        $since = now()->subDays($days);

        $merchants = $this->merchantQuery()
            ->with('userLimit')
            ->whereNotNull('last_active_at')
            ->where('last_active_at', '>=', $since)
            ->when($search, function ($query, $term) {
                $term = trim((string) $term);
                $query->where(function ($builder) use ($term) {
                    $builder->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('last_active_at')
            ->get();

        $rows = $merchants->map(fn (User $user) => $this->merchantRow($user));

        return $this->paginate(
            $rows,
            [
                'count' => $rows->count(),
                'days' => $days,
            ],
            $this->merchantColumns(),
            $page,
            $perPage
        );
    }

    /**
     * @return array{summary: array<string, mixed>, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}}
     */
    private function inactiveCandidates(int $page, int $perPage, ?string $search): array
    {
        $merchants = $this->merchantQuery()
            ->with('userLimit')
            ->when($search, function ($query, $term) {
                $term = trim((string) $term);
                $query->where(function ($builder) use ($term) {
                    $builder->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->get()
            ->filter(function (User $user) {
                return ! Engagement::isActive($user->last_active_at, $user->created_at);
            })
            ->map(function (User $user) {
                $row = $this->merchantRow($user);
                $reasons = [];
                $priority = 0;

                $reasons[] = 'خامل أكثر من 5 شهور';
                $priority += 10;

                $customers = (int) ($row['customers_used'] ?? 0);
                $installments = (int) ($row['installments_used'] ?? 0);
                if ($customers === 0) {
                    $reasons[] = 'بدون عملاء';
                    $priority += 20;
                }
                if ($installments === 0) {
                    $reasons[] = 'بدون أقساط';
                    $priority += 20;
                }
                if ($user->last_active_at === null && $user->created_at?->lt(now()->subDays(30))) {
                    $reasons[] = 'لم يسجّل نشاطاً منذ التسجيل';
                    $priority += 15;
                }
                if ($user->trial_used_at) {
                    $reasons[] = 'استخدم تجربة مجانية';
                    $priority += 5;
                }

                $row['reasons'] = implode(' · ', $reasons);
                $row['priority'] = $priority;
                $row['days_inactive'] = $this->daysInactive($user);

                return $row;
            })
            ->sortByDesc('priority')
            ->values();

        return $this->paginate(
            $merchants,
            [
                'count' => $merchants->count(),
                'safe_empty_count' => $merchants->filter(
                    fn ($row) => (int) $row['customers_used'] === 0 && (int) $row['installments_used'] === 0
                )->count(),
            ],
            array_merge($this->merchantColumns(), [
                ['key' => 'days_inactive', 'label' => 'أيام الخمول'],
                ['key' => 'reasons', 'label' => 'أسباب الترشيح'],
                ['key' => 'priority', 'label' => 'أولوية'],
            ]),
            $page,
            $perPage
        );
    }

    /**
     * @return array{summary: array<string, mixed>, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}}
     */
    private function resourceUsage(int $page, int $perPage, int $minUsage, ?string $search): array
    {
        $minUsage = max(0, $minUsage);

        $rows = $this->merchantQuery()
            ->with('userLimit')
            ->when($search, function ($query, $term) {
                $term = trim((string) $term);
                $query->where(function ($builder) use ($term) {
                    $builder->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->get()
            ->map(function (User $user) {
                $row = $this->merchantRow($user);
                $row['usage_score'] = (int) $row['customers_used'] + (int) $row['installments_used'];
                $row['is_inactive'] = ! Engagement::isActive($user->last_active_at, $user->created_at);

                return $row;
            })
            ->filter(fn ($row) => (int) $row['usage_score'] >= $minUsage)
            ->sortByDesc('usage_score')
            ->values();

        return $this->paginate(
            $rows,
            [
                'count' => $rows->count(),
                'min_usage' => $minUsage,
                'top_usage' => (int) ($rows->first()['usage_score'] ?? 0),
            ],
            array_merge($this->merchantColumns(), [
                ['key' => 'usage_score', 'label' => 'إجمالي الاستخدام'],
                ['key' => 'is_inactive', 'label' => 'خامل؟'],
            ]),
            $page,
            $perPage
        );
    }

    /**
     * @return array{summary: array<string, mixed>, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}}
     */
    private function subscriptions(): array
    {
        $merchants = $this->merchantQuery()->with('userLimit')->get();
        $byPlan = [];
        $active = 0;
        $expired = 0;
        $none = 0;

        foreach ($merchants as $merchant) {
            $limit = $merchant->userLimit;
            if (! $limit) {
                $none++;
                $key = 'بدون خطة';
            } else {
                $key = trim((string) ($limit->subscription_name ?: $limit->subscription_slug ?: 'غير مسمى')) ?: 'غير مسمى';
                if ($limit->status === 'active') {
                    $active++;
                } else {
                    $expired++;
                }
            }
            $byPlan[$key] = ($byPlan[$key] ?? 0) + 1;
        }

        arsort($byPlan);
        $rows = collect($byPlan)->map(fn ($count, $plan) => [
            'plan' => $plan,
            'merchants' => $count,
        ])->values();

        return $this->paginate(
            $rows,
            [
                'plans_count' => $rows->count(),
                'active_limits' => $active,
                'inactive_limits' => $expired,
                'no_limit' => $none,
            ],
            [
                ['key' => 'plan', 'label' => 'الخطة'],
                ['key' => 'merchants', 'label' => 'عدد التجار'],
            ],
            1,
            100
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function merchantQuery()
    {
        return User::query()
            ->where('role', UserRole::User)
            ->where(function ($query) {
                $query->where('is_platform_admin', false)
                    ->orWhereNull('is_platform_admin');
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function merchantRow(User $user): array
    {
        $limit = $user->userLimit;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'last_active_at' => $user->last_active_at?->toDateTimeString(),
            'created_at' => $user->created_at?->toDateString(),
            'plan' => $limit?->subscription_name ?: ($limit?->subscription_slug ?: '—'),
            'plan_status' => $limit?->status ?: 'none',
            'customers_used' => (int) ($limit->customers_used ?? 0),
            'installments_used' => (int) ($limit->installments_used ?? 0),
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function merchantColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'المعرف'],
            ['key' => 'name', 'label' => 'الاسم'],
            ['key' => 'email', 'label' => 'البريد'],
            ['key' => 'phone', 'label' => 'الهاتف'],
            ['key' => 'plan', 'label' => 'الخطة'],
            ['key' => 'plan_status', 'label' => 'حالة الخطة'],
            ['key' => 'customers_used', 'label' => 'عملاء'],
            ['key' => 'installments_used', 'label' => 'أقساط'],
            ['key' => 'last_active_at', 'label' => 'آخر نشاط'],
            ['key' => 'created_at', 'label' => 'تاريخ التسجيل'],
        ];
    }

    private function daysInactive(User $user): int
    {
        $anchor = $user->last_active_at ?? $user->created_at;
        if (! $anchor instanceof Carbon) {
            return 0;
        }

        return max(0, $anchor->diffInDays(now()));
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
