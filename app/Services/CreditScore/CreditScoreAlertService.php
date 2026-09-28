<?php

namespace App\Services\CreditScore;

use App\Models\Customer;
use App\Models\CustomerCreditScore;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Sends in-app alerts when score movement crosses configured thresholds.
 * Uses a fingerprint in notification data to reduce duplicate spam.
 */
class CreditScoreAlertService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function maybeNotify(
        Customer $customer,
        CustomerCreditScore $current,
        ?CustomerCreditScore $previous
    ): void {
        $vendor = $customer->user_id ? User::find($customer->user_id) : null;
        if ($vendor === null) {
            return;
        }

        $config = (array) config('credit_score.alerts', []);
        $significant = (int) ($config['significant_change_points'] ?? 30);
        $highRiskLevels = $config['high_risk_levels'] ?? ['high', 'very_high'];
        $dpdMilestones = $config['dpd_milestones'] ?? [30, 60, 90];

        $change = (int) $current->score_change;
        $prevScore = $previous !== null ? (int) $previous->score : null;
        $prevRisk = $previous?->risk_level;

        if ($change >= $significant) {
            $this->notifyOnce(
                $vendor,
                $customer,
                'credit_score_improved',
                'تحسّن التقييم الائتماني الداخلي',
                "ارتفع Internal Credit Score للعميل {$customer->name} بمقدار +{$change} نقطة ({$current->score}/850).",
                [
                    'customer_id' => $customer->id,
                    'score' => $current->score,
                    'change' => $change,
                    'fingerprint' => "improve:{$customer->id}:{$current->id}",
                ]
            );
        } elseif ($change <= -$significant) {
            $this->notifyOnce(
                $vendor,
                $customer,
                'credit_score_declined',
                'انخفاض التقييم الائتماني الداخلي',
                "انخفض Internal Credit Score للعميل {$customer->name} بمقدار {$change} نقطة ({$current->score}/850).",
                [
                    'customer_id' => $customer->id,
                    'score' => $current->score,
                    'change' => $change,
                    'fingerprint' => "decline:{$customer->id}:{$current->id}",
                ]
            );
        }

        if (in_array($current->risk_level, $highRiskLevels, true)
            && ($prevRisk === null || ! in_array($prevRisk, $highRiskLevels, true))) {
            $this->notifyOnce(
                $vendor,
                $customer,
                'credit_score_high_risk',
                'دخول نطاق مخاطر مرتفع',
                "العميل {$customer->name} دخل نطاق Internal Risk Level: {$current->risk_level}.",
                [
                    'customer_id' => $customer->id,
                    'risk_level' => $current->risk_level,
                    'score' => $current->score,
                    'fingerprint' => "risk:{$customer->id}:{$current->risk_level}:{$current->id}",
                ]
            );
        }

        foreach ($dpdMilestones as $milestone) {
            $milestone = (int) $milestone;
            $prevDpd = (int) ($previous->current_max_dpd ?? 0);
            $currDpd = (int) $current->current_max_dpd;
            if ($currDpd >= $milestone && $prevDpd < $milestone) {
                $this->notifyOnce(
                    $vendor,
                    $customer,
                    'credit_score_dpd',
                    'تأخير سداد مرتفع',
                    "العميل {$customer->name} تجاوز {$milestone}+ يوم تأخير (DPD).",
                    [
                        'customer_id' => $customer->id,
                        'dpd' => $currDpd,
                        'milestone' => $milestone,
                        'fingerprint' => "dpd:{$customer->id}:{$milestone}:{$current->id}",
                    ]
                );
            }
        }

        $prevOverdue = (float) ($previous->current_overdue_amount ?? 0);
        if ($prevOverdue > 0 && (float) $current->current_overdue_amount <= 0) {
            $this->notifyOnce(
                $vendor,
                $customer,
                'credit_score_overdue_cleared',
                'تم إغلاق المتأخرات',
                "العميل {$customer->name} أغلق جميع الأقساط المتأخرة.",
                [
                    'customer_id' => $customer->id,
                    'fingerprint' => "cleared:{$customer->id}:{$current->id}",
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notifyOnce(
        User $vendor,
        Customer $customer,
        string $type,
        string $title,
        string $message,
        array $data
    ): void {
        try {
            $this->notifications->create($vendor, $type, $title, $message, $data, enforceLimits: false);
        } catch (\Throwable $e) {
            Log::warning('Credit score alert failed', [
                'customer_id' => $customer->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
