<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerCreditScoreResource;
use App\Http\Traits\ApiResponse;
use App\Models\Customer;
use App\Models\CustomerCreditScore;
use App\Services\CreditScore\CreditScoreAnalyticsService;
use App\Services\CreditScore\CreditScoreInfrastructure;
use App\Services\CreditScore\CreditScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class CreditScoreController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CreditScoreService $creditScoreService,
        private readonly CreditScoreAnalyticsService $analyticsService,
    ) {
    }

    public function profile(int $customerId, Request $request): JsonResponse
    {
        if (! CreditScoreInfrastructure::schemaReady()) {
            return $this->errorResponse(
                CreditScoreInfrastructure::schemaErrorMessage(),
                503
            );
        }

        $customer = $this->findAuthorizedCustomer($customerId, $request);

        try {
            $snapshot = $this->resolveCustomerSnapshot($customer);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse(
                CreditScoreInfrastructure::userFacingError($e, (bool) config('app.debug')),
                CreditScoreInfrastructure::isSchemaException($e) ? 503 : 500
            );
        }

        $snapshot->loadMissing('modelVersion');

        return $this->successResponse(
            new CustomerCreditScoreResource($snapshot),
            'تم جلب ملف التقييم الائتماني للعميل بنجاح'
        );
    }

    public function history(int $customerId, Request $request): JsonResponse
    {
        $customer = $this->findAuthorizedCustomer($customerId, $request);

        $days = min(max((int) $request->query('days', 365), 7), 3650);

        $history = CustomerCreditScore::query()
            ->where('customer_id', $customer->id)
            ->where('calculated_at', '>=', now()->subDays($days))
            ->orderBy('calculated_at')
            ->get(['id', 'score', 'raw_score', 'risk_level', 'score_change', 'calculated_at']);

        return $this->successResponse([
            'customer_id' => $customer->id,
            'days' => $days,
            'points' => $history->map(fn ($row) => [
                'score' => (int) $row->score,
                'risk_level' => $row->risk_level,
                'score_change' => (int) $row->score_change,
                'calculated_at' => $row->calculated_at?->toISOString(),
            ]),
        ], 'تم جلب سجل Internal Credit Score بنجاح');
    }

    public function recalculate(int $customerId, Request $request): JsonResponse
    {
        if (! CreditScoreInfrastructure::schemaReady()) {
            return $this->errorResponse(
                CreditScoreInfrastructure::schemaErrorMessage(),
                503
            );
        }

        $customer = $this->findAuthorizedCustomer($customerId, $request);

        try {
            $snapshot = $this->creditScoreService->recalculate(
                $customer,
                $request->user(),
                manual: true
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse(
                CreditScoreInfrastructure::userFacingError($e, (bool) config('app.debug')),
                CreditScoreInfrastructure::isSchemaException($e) ? 503 : 500
            );
        }

        $snapshot->loadMissing('modelVersion');

        return $this->successResponse(
            new CustomerCreditScoreResource($snapshot),
            'تم إعادة حساب التقييم الائتماني الداخلي بنجاح'
        );
    }

    public function analyticsDashboard(Request $request): JsonResponse
    {
        if (! CreditScoreInfrastructure::schemaReady()) {
            return $this->errorResponse(
                CreditScoreInfrastructure::schemaErrorMessage(),
                503
            );
        }

        $data = $this->analyticsService->dashboard($request->user());
        $trendDays = min(max((int) $request->query('trend_days', 30), 7), 365);
        $data['score_trend'] = $this->analyticsService->scoreTrend($request->user(), $trendDays);

        return $this->successResponse($data, 'تم جلب Credit Analytics بنجاح');
    }

    public function reportList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report' => ['required', 'string', 'in:high_risk,improving,declining,excellent_payment,serious_delinquency,thin_file,overdue'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $limit = (int) ($validated['limit'] ?? 100);
        $query = $this->analyticsService->latestScoresQuery($request->user());

        match ($validated['report']) {
            'high_risk' => $query->whereIn('customer_credit_scores.risk_level', ['high', 'very_high']),
            'improving' => $query->where('customer_credit_scores.score_change', '>', 0),
            'declining' => $query->where('customer_credit_scores.score_change', '<', 0),
            'excellent_payment' => $query->where('customer_credit_scores.payment_history_score', '>=', 90),
            'serious_delinquency' => $query->where('customer_credit_scores.current_max_dpd', '>=', 90),
            'thin_file' => $query->where('customer_credit_scores.thin_file', true),
            'overdue' => $query->where('customer_credit_scores.current_overdue_count', '>', 0),
            default => null,
        };

        $rows = $query->with('customer:id,name,phone')
            ->orderByDesc('customer_credit_scores.calculated_at')
            ->limit($limit)
            ->get();

        return $this->successResponse([
            'report' => $validated['report'],
            'items' => $rows->map(fn (CustomerCreditScore $row) => [
                'customer_id' => $row->customer_id,
                'customer_name' => $row->customer?->name,
                'score' => (int) $row->score,
                'risk_level' => $row->risk_level,
                'score_change' => (int) $row->score_change,
                'current_overdue_amount' => (float) $row->current_overdue_amount,
                'calculated_at' => $row->calculated_at?->toISOString(),
            ]),
        ], 'تم جلب التقرير بنجاح');
    }

    public function exportPdf(int $customerId, Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $customer = $this->findAuthorizedCustomer($customerId, $request);
        $snapshot = $customer->currentCreditScore ?? $this->creditScoreService->recalculate($customer);
        $snapshot->loadMissing('modelVersion');

        $resource = (new CustomerCreditScoreResource($snapshot))->resolve();
        $html = $this->buildReportHtml($customer, $resource);

        try {
            $mpdf = new Mpdf([
                'tempDir' => storage_path('app/mpdf'),
                'mode' => 'utf-8',
                'format' => 'A4',
            ]);
            $mpdf->WriteHTML($html);
            $binary = $mpdf->Output('', Destination::STRING_RETURN);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('تعذر إنشاء تقرير PDF', 500);
        }

        $filename = 'internal-credit-report-'.$customer->id.'.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function findAuthorizedCustomer(int $customerId, Request $request): Customer
    {
        $customer = Customer::query()->find($customerId);
        if ($customer === null) {
            abort(404, 'العميل غير موجود');
        }

        $this->authorize('view', $customer);

        return $customer;
    }

    private function resolveCustomerSnapshot(Customer $customer): CustomerCreditScore
    {
        $snapshot = $customer->currentCreditScore;

        if ($snapshot === null && $customer->current_credit_score_id !== null) {
            $snapshot = CustomerCreditScore::query()->find($customer->current_credit_score_id);
        }

        if ($snapshot === null) {
            $snapshot = CustomerCreditScore::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('calculated_at')
                ->orderByDesc('id')
                ->first();
        }

        if ($snapshot !== null) {
            if ($customer->current_credit_score_id !== $snapshot->id) {
                $customer->forceFill(['current_credit_score_id' => $snapshot->id])->saveQuietly();
            }

            return $snapshot;
        }

        return $this->creditScoreService->recalculate($customer);
    }

    /**
     * @param  array<string, mixed>  $score
     */
    private function buildReportHtml(Customer $customer, array $score): string
    {
        $positives = implode('', array_map(
            fn ($line) => '<li>'.e($line).'</li>',
            $score['positive_factors'] ?? []
        ));
        $negatives = implode('', array_map(
            fn ($line) => '<li>'.e($line).'</li>',
            $score['negative_factors'] ?? []
        ));

        $components = $score['components'] ?? [];
        $compRows = '';
        foreach ($components as $key => $value) {
            if ($value === null) {
                continue;
            }
            $compRows .= '<tr><td>'.e($key).'</td><td>'.e((string) $value).'/100</td></tr>';
        }

        $name = e($customer->name);
        $risk = e((string) ($score['risk_label'] ?? $score['risk_level']));
        $confidence = e((string) $score['confidence_level']);
        $calculated = e((string) ($score['calculated_at'] ?? ''));
        $scoreVal = (int) $score['score'];
        $scoreMax = (int) $score['score_max'];
        $change = (int) $score['score_change'];

        return <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head><meta charset="utf-8"><style>
body{font-family:dejavusans,sans-serif;font-size:12px;color:#111}
h1{font-size:18px;color:#1B4F9C}
.box{border:1px solid #ddd;padding:12px;margin:12px 0;border-radius:6px}
.disclaimer{background:#fff7ed;border-color:#fdba74;font-size:11px}
table{width:100%;border-collapse:collapse} td,th{border:1px solid #ddd;padding:6px}
</style></head>
<body>
<h1>Internal Credit Assessment</h1>
<p class="disclaimer box"><strong>تنبيه:</strong> هذا تقييم ائتماني داخلي (Internal Credit Score) مبني على بيانات التقسيط داخل النظام فقط. ليس I-Score رسميًا ولا صادرًا عن الشركة المصرية للاستعلام الائتماني.</p>
<div class="box">
<p><strong>العميل:</strong> {$name}</p>
<p><strong>Internal Credit Score:</strong> {$scoreVal}/{$scoreMax}</p>
<p><strong>Internal Risk Level:</strong> {$risk}</p>
<p><strong>Confidence:</strong> {$confidence}</p>
<p><strong>Score change:</strong> {$change}</p>
<p><strong>Last calculation:</strong> {$calculated}</p>
</div>
<h2>Score breakdown</h2>
<table>{$compRows}</table>
<h2>Positive factors</h2>
<ul>{$positives}</ul>
<h2>Negative factors</h2>
<ul>{$negatives}</ul>
</body></html>
HTML;
    }
}
