<?php

namespace App\Services\CreditScore;

use App\Models\Customer;
use App\Models\Installment;
use Carbon\CarbonImmutable;

/**
 * Estimates whether a customer can reasonably take on installment plans at this
 * merchant, using registered salary (optional) and active installment exposure.
 * This is internal policy — not I-Score or bureau data.
 */
class InstallmentAffordabilityService
{
    /**
     * @return array<string, mixed>
     */
    public function assess(Customer $customer): array
    {
        $hasInstallments = Installment::query()
            ->where('customer_id', $customer->id)
            ->exists();

        $salary = $customer->monthly_salary !== null
            ? (float) $customer->monthly_salary
            : null;

        $monthlyObligation = $hasInstallments
            ? $this->estimateMonthlyObligation($customer)
            : 0.0;

        $overdueAmount = $hasInstallments
            ? $this->currentOverdueAmount($customer)
            : 0.0;

        $maxDti = (float) config('credit_score.affordability.max_dti_ratio', 0.40);
        $dti = ($salary !== null && $salary > 0)
            ? round($monthlyObligation / $salary, 4)
            : null;

        $reasons = [];
        $canAccept = null;

        if (! $hasInstallments) {
            $reasons[] = 'لا توجد أقساط مسجلة — لا يُعرض تقييم ائتماني.';
            if ($salary === null || $salary <= 0) {
                $canAccept = null;
                $reasons[] = 'أدخل المرتب الشهري لتقدير إمكانية فتح قسط جديد.';
            } else {
                $canAccept = true;
                $reasons[] = 'لا التزامات أقساط حالية — يمكن البدء مع مراعاة المرتب.';
            }
        } else {
            if ($overdueAmount > 0 && (bool) config('credit_score.affordability.block_when_overdue', true)) {
                $canAccept = false;
                $reasons[] = 'يوجد متأخرات حالية — يُفضّل السداد قبل قسط إضافي.';
            } elseif ($salary === null || $salary <= 0) {
                $canAccept = null;
                $reasons[] = 'أدخل المرتب الشهري لتقدير إمكانية قبول قسط جديد.';
            } elseif ($dti !== null && $dti > $maxDti) {
                $canAccept = false;
                $reasons[] = sprintf(
                    'الالتزام الشهري التقريبي (%.0f ج.م) يتجاوز %.0f%% من المرتب.',
                    $monthlyObligation,
                    $maxDti * 100
                );
            } else {
                $canAccept = true;
                $reasons[] = 'الالتزام الحالي ضمن حدود المرتب المسجّل (تقدير داخلي).';
            }
        }

        return [
            'has_installment_history' => $hasInstallments,
            'score_applicable' => $hasInstallments,
            'monthly_salary' => $salary,
            'estimated_monthly_obligation' => round($monthlyObligation, 2),
            'current_overdue_amount' => round($overdueAmount, 2),
            'dti_ratio' => $dti,
            'max_dti_ratio' => $maxDti,
            'can_accept_installment' => $canAccept,
            'summary' => $this->summaryLabel($canAccept, $hasInstallments),
            'reasons' => $reasons,
        ];
    }

    private function summaryLabel(?bool $canAccept, bool $hasInstallments): string
    {
        if (! $hasInstallments) {
            return 'بدون أقساط — لا score';
        }
        if ($canAccept === null) {
            return 'أدخل المرتب للتقدير';
        }
        if ($canAccept) {
            return 'يمكن قبول قسط (تقدير داخلي)';
        }

        return 'غير مناسب لقسط جديد الآن';
    }

    private function estimateMonthlyObligation(Customer $customer): float
    {
        $today = CarbonImmutable::now()->startOfDay();
        $horizon = $today->addDays(30);

        $installments = Installment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->with(['items' => fn ($q) => $q->orderBy('due_date')])
            ->get();

        $monthly = 0.0;

        foreach ($installments as $installment) {
            foreach ($installment->items as $item) {
                if ($item->status === 'paid' || $item->paid_at !== null) {
                    continue;
                }
                if ($item->due_date === null) {
                    continue;
                }
                $due = CarbonImmutable::parse($item->due_date)->startOfDay();
                if ($due->lessThan($today)) {
                    $monthly += (float) $item->amount;
                } elseif ($due->lessThanOrEqualTo($horizon)) {
                    $monthly += (float) $item->amount;
                }
            }
        }

        if ($monthly > 0) {
            return $monthly;
        }

        // Fallback: average remaining installment payment per active plan.
        foreach ($installments as $installment) {
            $unpaid = $installment->items->filter(
                fn ($item) => $item->status !== 'paid' && $item->paid_at === null
            );
            if ($unpaid->isEmpty()) {
                continue;
            }
            $remaining = $unpaid->sum(fn ($item) => (float) $item->amount);
            $monthly += $remaining / max(1, $unpaid->count());
        }

        return $monthly;
    }

    private function currentOverdueAmount(Customer $customer): float
    {
        $today = CarbonImmutable::now()->startOfDay();

        $installments = Installment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->with('items')
            ->get();

        $overdue = 0.0;
        foreach ($installments as $installment) {
            foreach ($installment->items as $item) {
                if ($item->status === 'paid' || $item->paid_at !== null) {
                    continue;
                }
                if ($item->due_date === null) {
                    continue;
                }
                $due = CarbonImmutable::parse($item->due_date)->startOfDay();
                if ($due->lessThan($today)) {
                    $overdue += (float) $item->amount;
                }
            }
        }

        return $overdue;
    }
}
