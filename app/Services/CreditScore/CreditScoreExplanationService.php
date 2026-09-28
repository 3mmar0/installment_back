<?php

namespace App\Services\CreditScore;

use App\Models\CustomerCreditScore;
use App\Services\CreditScore\Support\CreditScoreResult;
use App\Services\CreditScore\Support\CustomerCreditData;

/**
 * Builds human-readable positive/negative factors and score-change reasons from
 * real metrics. Never invents reasons that the data does not support.
 */
class CreditScoreExplanationService
{
    /**
     * @return string[]
     */
    public function positiveFactors(CreditScoreResult $result, CustomerCreditData $data): array
    {
        $factors = [];
        $payment = $result->metrics['payment_history'] ?? [];

        $onTimeRatio = $payment['on_time_ratio'] ?? null;
        if ($onTimeRatio !== null && $onTimeRatio >= 0.9) {
            $pct = (int) round($onTimeRatio * 100);
            $factors[] = "{$pct}% من الأقساط سُددت في موعدها.";
        }

        if (($payment['current_overdue_count'] ?? 0) === 0 && ($data->dueCount() ?? 0) > 0) {
            $factors[] = 'لا توجد أقساط متأخرة حاليًا.';
        }

        $consecutive = (int) ($payment['consecutive_on_time'] ?? 0);
        if ($consecutive >= 3) {
            $factors[] = "{$consecutive} دفعة متتالية في الموعد.";
        }

        if ($result->completedContracts >= 1) {
            $factors[] = $result->completedContracts === 1
                ? 'عقد واحد اكتمل بنجاح.'
                : "{$result->completedContracts} عقود اكتملت بنجاح.";
        }

        if ($result->historyMonths >= 24 && $onTimeRatio !== null && $onTimeRatio >= 0.85) {
            $factors[] = 'تاريخ سداد إيجابي طويل.';
        }

        if (($payment['late_90_plus'] ?? 0) === 0 && ($payment['paid_late'] ?? 0) > 0) {
            $factors[] = 'لا توجد تأخيرات خطيرة (+90 يوم) في السجل.';
        }

        return array_values(array_unique($factors));
    }

    /**
     * @return string[]
     */
    public function negativeFactors(CreditScoreResult $result, CustomerCreditData $data): array
    {
        $factors = [];
        $payment = $result->metrics['payment_history'] ?? [];
        $activity = $result->metrics['credit_activity'] ?? [];

        $lateRecent = (int) ($payment['late_8_30'] ?? 0) + (int) ($payment['late_1_7'] ?? 0);
        if ($lateRecent >= 1 && ($payment['late_ratio'] ?? 0) > 0) {
            $factors[] = 'تأخيرات حديثة في السداد.';
        }

        if (($payment['current_overdue_count'] ?? 0) > 0) {
            $factors[] = 'يوجد رصيد متأخر حاليًا.';
        }

        if ($result->activeContracts >= 4) {
            $factors[] = 'عدد كبير من خطط الأقساط النشطة.';
        }

        if (($activity['new_contracts_90d'] ?? 0) >= 3) {
            $factors[] = 'زيادة حديثة في نشاط التمويل.';
        }

        if ($result->thinFile) {
            $factors[] = 'تاريخ ائتماني محدود (ملف رقيق).';
        }

        if (($payment['late_90_plus'] ?? 0) >= 1) {
            $factors[] = 'تأخير خطير (+90 يوم) مسجل.';
        }

        $other = $result->metrics['other'] ?? [];
        if (($other['duplicate_identity'] ?? false) === true) {
            $factors[] = 'مؤشرات محتملة لتكرار الهوية ضمن سجلاتك.';
        }

        return array_values(array_unique($factors));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function changeReasons(
        CreditScoreResult $current,
        ?CustomerCreditScore $previous
    ): array {
        if ($previous === null) {
            return [];
        }

        $reasons = [];
        $delta = $current->score - (int) $previous->score;
        if ($delta === 0) {
            return [];
        }

        $prevMetrics = is_array($previous->metrics) ? $previous->metrics : [];
        $prevPayment = $prevMetrics['payment_history'] ?? [];
        $currPayment = $current->metrics['payment_history'] ?? [];

        $prevOnTime = (float) ($prevPayment['on_time_ratio'] ?? 0);
        $currOnTime = (float) ($currPayment['on_time_ratio'] ?? 0);
        if ($currOnTime > $prevOnTime + 0.01) {
            $reasons[] = ['type' => 'positive', 'message' => 'زيادة نسبة السداد في الموعد.'];
        }

        $prevOverdue = (float) ($previous->current_overdue_amount ?? 0);
        if ($prevOverdue > 0 && $current->currentOverdueAmount <= 0) {
            $reasons[] = ['type' => 'positive', 'message' => 'تم تصفية الرصيد المتأخر.'];
        }

        if ($current->currentOverdueAmount > $prevOverdue + 0.01) {
            $reasons[] = ['type' => 'negative', 'message' => 'ارتفع الرصيد المتأخر المستحق.'];
        }

        if ($current->currentMaxDpd > (int) $previous->current_max_dpd) {
            $reasons[] = [
                'type' => 'negative',
                'message' => "زادت أيام التأخير الحالية إلى {$current->currentMaxDpd} يومًا.",
            ];
        }

        if ($current->activeContracts > (int) $previous->active_contracts) {
            $reasons[] = ['type' => 'negative', 'message' => 'تمت إضافة خطة تقسيط جديدة.'];
        }

        if ($prevPayment !== [] && ($prevPayment['late_90_plus'] ?? 0) > ($currPayment['late_90_plus'] ?? 0)) {
            $reasons[] = ['type' => 'positive', 'message' => 'تراجع وزن تأخيرات قديمة في الحساب.'];
        }

        return $reasons;
    }
}
