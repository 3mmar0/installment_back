<?php

namespace App\Services\CreditScore;

use App\Models\Customer;
use App\Support\Pdf\RtlPdfDocument;

final class CreditScoreCustomerPdfBuilder
{
    /** @var array<string, string> */
    private const COMPONENT_LABELS = [
        'payment_history' => 'سجل السداد (35%)',
        'financial_burden' => 'العبء المالي (25%)',
        'credit_history' => 'تاريخ الائتمان (15%)',
        'credit_activity' => 'النشاط الائتماني (10%)',
        'payment_consistency' => 'انتظام السداد (10%)',
        'other' => 'مخاطر أخرى (5%)',
    ];

    /**
     * @param  array<string, mixed>  $score
     * @param  array<string, mixed>  $eligibility
     */
    public static function build(Customer $customer, array $score, array $eligibility = []): string
    {
        $e = [RtlPdfDocument::class, 'e'];
        $generatedAt = now()->format('Y-m-d H:i');
        $subtitle = 'تقرير التقييم الائتماني الداخلي · تاريخ التصدير: '.$generatedAt;

        $scoreVal = (int) ($score['score'] ?? 0);
        $scoreMax = (int) ($score['score_max'] ?? 850);
        $risk = (string) ($score['risk_label'] ?? $score['risk_level'] ?? '—');
        $confidence = (string) ($score['confidence_level'] ?? '—');
        $change = (int) ($score['score_change'] ?? 0);
        $changeLabel = $change > 0 ? '+'.$change : (string) $change;

        $customerBlock = self::customerInfoTable($customer, $e);
        $scoreHero = self::scoreHero($scoreVal, $scoreMax, $risk, $confidence, $changeLabel, $e);
        $kpiCards = self::kpiCards($score, $e);
        $components = self::componentsTable($score['components'] ?? [], $e);
        $factors = self::factorsBlock($score, $e);
        $eligibilityBlock = self::eligibilityBlock($eligibility, $e);

        $disclaimer = '<div class="disclaimer"><strong>تنبيه:</strong> '
            .'تقييم ائتماني داخلي (Internal Credit Score) من بيانات التقسيط داخل النظام فقط. '
            .'ليس I-Score رسميًا ولا صادرًا عن الشركة المصرية للاستعلام الائتماني.</div>';

        $footer = '<p class="footer-note">'
            .RtlPdfDocument::e(config('app.name', 'اقساطي'))
            .' · Internal Credit Score · '
            .RtlPdfDocument::e($generatedAt)
            .'</p>';

        $body = $disclaimer.$customerBlock.$scoreHero.$kpiCards.$eligibilityBlock
            .'<div class="section-title">تفصيل المكونات</div>'.$components
            .$factors.$footer;

        return RtlPdfDocument::documentShell(
            'ملف التقييم الائتماني — '.$customer->name,
            $subtitle,
            $body
        );
    }

    /**
     * @param  callable  $e
     */
    private static function customerInfoTable(Customer $customer, callable $e): string
    {
        $rows = [
            ['العميل', $customer->name],
            ['الهاتف', $customer->phone ?? '—'],
            ['الرقم القومي', $customer->national_id ?? '—'],
            ['الوظيفة', $customer->job ?? '—'],
            ['المرتب الشهري', $customer->monthly_salary !== null
                ? number_format((float) $customer->monthly_salary, 2).' ج.م'
                : '—'],
            ['آخر تحديث للبيانات', $customer->updated_at?->format('Y-m-d H:i') ?? '—'],
        ];

        $html = '<table class="data"><tbody>';
        foreach ($rows as [$label, $value]) {
            $html .= '<tr><th style="width:28%">'.$e($label).'</th><td>'.$e($value).'</td></tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * @param  callable  $e
     */
    private static function scoreHero(
        int $score,
        int $max,
        string $risk,
        string $confidence,
        string $change,
        callable $e
    ): string {
        return <<<HTML
<div class="score-hero">
  <div class="num">{$e($score)}<span class="scale"> / {$e($max)}</span></div>
  <p style="margin:8px 0 0;font-size:11pt"><strong>مستوى المخاطرة:</strong> {$e($risk)}</p>
  <p style="margin:4px 0;font-size:10pt"><strong>موثوقية البيانات:</strong> {$e($confidence)} · <strong>التغيّر:</strong> <span class="ltr">{$e($change)}</span></p>
</div>
HTML;
    }

    /**
     * @param  array<string, mixed>  $score
     * @param  callable  $e
     */
    private static function kpiCards(array $score, callable $e): string
    {
        $items = [
            ['المتأخرات الحالية', number_format((float) ($score['current_overdue_amount'] ?? 0), 2).' ج.م'],
            ['أقصى أيام تأخير', (string) ((int) ($score['max_dpd'] ?? 0)).' يوم'],
            ['عقود نشطة', (string) ((int) ($score['active_contracts'] ?? 0))],
        ];

        $cells = '';
        foreach ($items as [$k, $v]) {
            $cells .= '<td><div class="k">'.$e($k).'</div><div class="v">'.$e($v).'</div></td>';
        }

        return '<table class="card-grid"><tr>'.$cells.'</tr></table>';
    }

    /**
     * @param  array<string, mixed>  $components
     * @param  callable  $e
     */
    private static function componentsTable(array $components, callable $e): string
    {
        $rows = '';
        foreach (self::COMPONENT_LABELS as $key => $label) {
            $val = $components[$key] ?? null;
            if ($val === null) {
                continue;
            }
            $num = (float) $val;
            $pct = max(0, min(100, $num));
            $rows .= '<tr><td>'.$e($label).'</td><td class="ltr" style="width:12%">'.$e(round($num)).'/100</td><td style="width:45%">'
                .'<div class="bar-wrap"><div class="bar-fill" style="width:'.$pct.'%"></div></div></td></tr>';
        }

        if ($rows === '') {
            return '<p>—</p>';
        }

        return '<table class="data"><thead><tr><th>المكون</th><th>الدرجة</th><th>الشريط</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /**
     * @param  array<string, mixed>  $score
     * @param  callable  $e
     */
    private static function factorsBlock(array $score, callable $e): string
    {
        $pos = $score['positive_factors'] ?? [];
        $neg = $score['negative_factors'] ?? [];

        $posLi = '';
        foreach ($pos as $line) {
            $posLi .= '<li>'.$e($line).'</li>';
        }
        $negLi = '';
        foreach ($neg as $line) {
            $negLi .= '<li>'.$e($line).'</li>';
        }

        return '<table class="card-grid"><tr>'
            .'<td><div class="section-title" style="margin-top:0;border:none">عوامل إيجابية</div>'
            .'<ul class="factors positive">'.($posLi ?: '<li>—</li>').'</ul></td>'
            .'<td><div class="section-title" style="margin-top:0;border:none">عوامل سلبية</div>'
            .'<ul class="factors negative">'.($negLi ?: '<li>—</li>').'</ul></td>'
            .'</tr></table>';
    }

    /**
     * @param  array<string, mixed>  $eligibility
     * @param  callable  $e
     */
    private static function eligibilityBlock(array $eligibility, callable $e): string
    {
        if ($eligibility === []) {
            return '';
        }

        $summary = (string) ($eligibility['summary'] ?? '');
        $reasons = $eligibility['reasons'] ?? [];
        $reasonHtml = '';
        foreach ($reasons as $r) {
            $reasonHtml .= '<li>'.$e($r).'</li>';
        }

        $salary = $eligibility['monthly_salary'] ?? null;
        $obligation = $eligibility['estimated_monthly_obligation'] ?? null;
        $extra = '';
        if ($salary !== null) {
            $extra = '<p style="font-size:9.5pt;margin-top:6px">المرتب: '
                .$e(number_format((float) $salary, 2)).' ج.م';
            if ($obligation !== null) {
                $extra .= ' · الالتزام الشهري التقريبي: '.$e(number_format((float) $obligation, 2)).' ج.م';
            }
            $extra .= '</p>';
        }

        return '<div class="section-title">إمكانية قبول قسط جديد (تقدير داخلي)</div>'
            .'<div class="disclaimer" style="background:#ecfdf5;border-color:#6ee7b7;color:#166534">'
            .'<strong>'.$e($summary).'</strong>'
            .'<ul class="factors" style="margin-top:6px">'.$reasonHtml.'</ul>'
            .$extra
            .'</div>';
    }
}
