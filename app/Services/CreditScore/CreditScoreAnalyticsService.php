<?php

namespace App\Services\CreditScore;

use App\Models\Customer;
use App\Models\CustomerCreditScore;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Portfolio-level Internal Credit Score analytics. Always uses the latest
 * snapshot per customer (via customers.current_credit_score_id), never historical
 * rows, so distribution counts are not inflated.
 */
class CreditScoreAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $user): array
    {
        $scores = $this->latestScoresQuery($user)->get();

        if ($scores->isEmpty()) {
            return $this->emptyDashboard();
        }

        $scoreValues = $scores->pluck('score')->map(fn ($s) => (int) $s)->sort()->values();
        $count = $scoreValues->count();
        $average = round($scoreValues->avg(), 1);
        $median = $this->median($scoreValues);

        $distribution = $this->scoreDistribution($scores);
        $riskDistribution = $scores->groupBy('risk_level')->map->count()->all();

        $improving = $scores->where('score_change', '>', 0)->count();
        $declining = $scores->where('score_change', '<', 0)->count();
        $overdueCustomers = $scores->where('current_overdue_count', '>', 0)->count();

        $onTimeRates = [];
        foreach ($scores as $row) {
            $ratio = data_get($row->metrics, 'payment_history.on_time_ratio');
            if ($ratio !== null) {
                $onTimeRates[] = (float) $ratio;
            }
        }

        $avgOnTime = $onTimeRates !== [] ? round(array_sum($onTimeRates) / count($onTimeRates) * 100, 1) : null;

        $dpdBuckets = $this->dpdBucketSummary($scores);

        return [
            'customers_scored' => $count,
            'average_score' => $average,
            'median_score' => $median,
            'highest_score' => (int) $scoreValues->max(),
            'lowest_score' => (int) $scoreValues->min(),
            'score_distribution' => $distribution,
            'risk_distribution' => $riskDistribution,
            'customers_improving' => $improving,
            'customers_declining' => $declining,
            'customers_with_overdue' => $overdueCustomers,
            'overdue_rate_percent' => $count > 0 ? round($overdueCustomers / $count * 100, 1) : 0,
            'average_on_time_rate_percent' => $avgOnTime,
            'average_score_change' => round($scores->avg('score_change'), 1),
            'thin_file_customers' => $scores->where('thin_file', true)->count(),
            'dpd_buckets' => $dpdBuckets,
            'total_overdue_amount' => round($scores->sum(fn ($s) => (float) $s->current_overdue_amount), 2),
            'average_dpd' => round($scores->avg('current_max_dpd'), 1),
        ];
    }

    /**
     * Average displayed score by day from historical snapshots (trend).
     *
     * @return array<int, array{date: string, average_score: float, count: int}>
     */
    public function scoreTrend(User $user, int $days = 30): array
    {
        $since = now()->subDays($days)->startOfDay();

        $customerIds = $this->customerIdsForUser($user);

        if ($customerIds->isEmpty()) {
            return [];
        }

        $rows = CustomerCreditScore::query()
            ->selectRaw('DATE(calculated_at) as day, AVG(score) as avg_score, COUNT(*) as cnt')
            ->whereIn('customer_id', $customerIds)
            ->where('calculated_at', '>=', $since)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return $rows->map(fn ($row) => [
            'date' => (string) $row->day,
            'average_score' => round((float) $row->avg_score, 1),
            'count' => (int) $row->cnt,
        ])->all();
    }

    /**
     * @return Collection<int, CustomerCreditScore>
     */
    public function latestScoresQuery(User $user)
    {
        $query = CustomerCreditScore::query()
            ->join('customers', 'customers.current_credit_score_id', '=', 'customer_credit_scores.id');

        if (! $user->canManageMerchantData()) {
            $query->where('customers.user_id', $user->id);
        }

        return $query->select('customer_credit_scores.*');
    }

    /**
     * @return Collection<int, int>
     */
    private function customerIdsForUser(User $user): Collection
    {
        $q = Customer::query()->whereNotNull('current_credit_score_id');
        if (! $user->canManageMerchantData()) {
            $q->where('user_id', $user->id);
        }

        return $q->pluck('id');
    }

    /**
     * @param  Collection<int, CustomerCreditScore>  $scores
     * @return array<string, int>
     */
    private function scoreDistribution(Collection $scores): array
    {
        $bands = [
            '300_499' => 0,
            '500_599' => 0,
            '600_649' => 0,
            '650_699' => 0,
            '700_749' => 0,
            '750_799' => 0,
            '800_850' => 0,
        ];

        foreach ($scores as $row) {
            $s = (int) $row->score;
            if ($s <= 499) {
                $bands['300_499']++;
            } elseif ($s <= 599) {
                $bands['500_599']++;
            } elseif ($s <= 649) {
                $bands['600_649']++;
            } elseif ($s <= 699) {
                $bands['650_699']++;
            } elseif ($s <= 749) {
                $bands['700_749']++;
            } elseif ($s <= 799) {
                $bands['750_799']++;
            } else {
                $bands['800_850']++;
            }
        }

        return $bands;
    }

    /**
     * @param  Collection<int, CustomerCreditScore>  $scores
     * @return array<string, array<string, int|float>>
     */
    private function dpdBucketSummary(Collection $scores): array
    {
        $buckets = [
            '1_7' => ['customers' => 0, 'amount' => 0.0],
            '8_30' => ['customers' => 0, 'amount' => 0.0],
            '31_60' => ['customers' => 0, 'amount' => 0.0],
            '61_90' => ['customers' => 0, 'amount' => 0.0],
            '90_plus' => ['customers' => 0, 'amount' => 0.0],
        ];

        foreach ($scores as $row) {
            if ((int) $row->current_overdue_count <= 0) {
                continue;
            }
            $dpd = (int) $row->current_max_dpd;
            $key = match (true) {
                $dpd >= 91 => '90_plus',
                $dpd >= 61 => '61_90',
                $dpd >= 31 => '31_60',
                $dpd >= 8 => '8_30',
                default => '1_7',
            };
            $buckets[$key]['customers']++;
            $buckets[$key]['amount'] += (float) $row->current_overdue_amount;
        }

        foreach ($buckets as $key => $values) {
            $buckets[$key]['amount'] = round($values['amount'], 2);
        }

        return $buckets;
    }

    /**
     * @param  Collection<int, int>  $scoreValues
     */
    private function median(Collection $scoreValues): float
    {
        $count = $scoreValues->count();
        if ($count === 0) {
            return 0.0;
        }
        $mid = (int) floor($count / 2);
        if ($count % 2 === 1) {
            return (float) $scoreValues[$mid];
        }

        return round(($scoreValues[$mid - 1] + $scoreValues[$mid]) / 2, 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboard(): array
    {
        return [
            'customers_scored' => 0,
            'average_score' => null,
            'median_score' => null,
            'highest_score' => null,
            'lowest_score' => null,
            'score_distribution' => [],
            'risk_distribution' => [],
            'customers_improving' => 0,
            'customers_declining' => 0,
            'customers_with_overdue' => 0,
            'overdue_rate_percent' => 0,
            'average_on_time_rate_percent' => null,
            'average_score_change' => 0,
            'thin_file_customers' => 0,
            'dpd_buckets' => [],
            'total_overdue_amount' => 0,
            'average_dpd' => 0,
        ];
    }
}
