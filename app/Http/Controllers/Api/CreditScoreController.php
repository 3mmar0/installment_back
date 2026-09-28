<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerCreditScoreResource;
use App\Http\Traits\ApiResponse;
use App\Models\Customer;
use App\Models\CustomerCreditScore;
use App\Models\User;
use App\Services\CreditScore\CreditScoreAnalyticsService;
use App\Services\CreditScore\CreditScoreCustomerPdfBuilder;
use App\Services\CreditScore\CreditScoreInfrastructure;
use App\Services\CreditScore\CreditScoreService;
use App\Services\CreditScore\InstallmentAffordabilityService;
use App\Support\Pdf\RtlPdfDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mpdf\Output\Destination;

class CreditScoreController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CreditScoreService $creditScoreService,
        private readonly CreditScoreAnalyticsService $analyticsService,
        private readonly InstallmentAffordabilityService $affordability,
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
        $eligibility = $this->affordability->assess($customer);

        if (! $eligibility['has_installment_history']) {
            $this->creditScoreService->clearCurrentScore($customer);

            return $this->successResponse(
                $this->profilePayloadWithoutScore($eligibility),
                'لا يوجد تقييم ائتماني بدون سجل أقساط — تم جلب تقدير القسط فقط'
            );
        }

        try {
            $snapshot = $this->resolveCustomerSnapshot($customer);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse(
                CreditScoreInfrastructure::userFacingError($e, (bool) config('app.debug')),
                CreditScoreInfrastructure::isSchemaException($e) ? 503 : 500
            );
        }

        if ($snapshot === null) {
            return $this->successResponse(
                $this->profilePayloadWithoutScore($eligibility),
                'تعذر حساب التقييم — تم جلب تقدير القسط'
            );
        }

        $snapshot->loadMissing('modelVersion');

        $payload = (new CustomerCreditScoreResource($snapshot))->resolve();
        $payload['score_applicable'] = true;
        $payload['installment_eligibility'] = $eligibility;

        return $this->successResponse(
            $payload,
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

        $eligibility = $this->affordability->assess($customer);

        if ($snapshot === null) {
            return $this->successResponse(
                $this->profilePayloadWithoutScore($eligibility),
                'لا يوجد تقييم بدون أقساط — تم تحديث تقدير القسط'
            );
        }

        $snapshot->loadMissing('modelVersion');

        $payload = (new CustomerCreditScoreResource($snapshot))->resolve();
        $payload['score_applicable'] = true;
        $payload['installment_eligibility'] = $eligibility;

        return $this->successResponse(
            $payload,
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
        $query = $this->reportQuery($request->user(), $validated['report']);

        $rows = $query->with('customer:id,name,phone,monthly_salary')
            ->orderByDesc('customer_credit_scores.calculated_at')
            ->limit($limit)
            ->get();

        return $this->successResponse([
            'report' => $validated['report'],
            'items' => $rows->map(fn (CustomerCreditScore $row) => [
                'customer_id' => $row->customer_id,
                'customer_name' => $row->customer?->name,
                'customer_phone' => $row->customer?->phone,
                'score' => (int) $row->score,
                'risk_level' => $row->risk_level,
                'risk_label' => $this->riskLabelForLevel($row->risk_level),
                'score_change' => (int) $row->score_change,
                'current_overdue_amount' => (float) $row->current_overdue_amount,
                'current_max_dpd' => (int) $row->current_max_dpd,
                'calculated_at' => $row->calculated_at?->toISOString(),
            ]),
        ], 'تم جلب التقرير بنجاح');
    }

    public function exportReportCsv(Request $request): \Illuminate\Http\Response|JsonResponse
    {
        if (! CreditScoreInfrastructure::schemaReady()) {
            return $this->errorResponse(
                CreditScoreInfrastructure::schemaErrorMessage(),
                503
            );
        }

        $validated = $request->validate([
            'report' => ['required', 'string', 'in:high_risk,improving,declining,excellent_payment,serious_delinquency,thin_file,overdue'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $limit = (int) ($validated['limit'] ?? 200);
        $query = $this->reportQuery($request->user(), $validated['report']);
        $rows = $query->with('customer:id,name,phone')
            ->orderByDesc('customer_credit_scores.calculated_at')
            ->limit($limit)
            ->get();

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return $this->errorResponse('تعذر إنشاء CSV', 500);
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'customer_id',
            'customer_name',
            'phone',
            'score',
            'risk_level',
            'score_change',
            'overdue_amount',
            'max_dpd',
            'calculated_at',
        ], ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row->customer_id,
                $row->customer?->name,
                $row->customer?->phone,
                (int) $row->score,
                $row->risk_level,
                (int) $row->score_change,
                (float) $row->current_overdue_amount,
                (int) $row->current_max_dpd,
                $row->calculated_at?->toISOString(),
            ], ',', '"', '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'credit-report-'.$validated['report'].'-'.date('Y-m-d').'.csv';

        return response($csv ?: '', 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportPdf(int $customerId, Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $customer = $this->findAuthorizedCustomer($customerId, $request);

        if (! $customer->installments()->exists()) {
            return $this->errorResponse('لا يمكن إنشاء تقرير بدون سجل أقساط للعميل', 422);
        }

        $snapshot = $customer->currentCreditScore ?? $this->creditScoreService->recalculate($customer);
        if ($snapshot === null) {
            return $this->errorResponse('لا يوجد تقييم محسوب لهذا العميل', 422);
        }
        $snapshot->loadMissing('modelVersion');

        $resource = (new CustomerCreditScoreResource($snapshot))->resolve();
        $eligibility = $this->affordability->assess($customer);
        $html = CreditScoreCustomerPdfBuilder::build($customer, $resource, $eligibility);

        try {
            $mpdf = RtlPdfDocument::createMpdf();
            $mpdf->SetHTMLFooter(
                '<div style="text-align:center;font-size:8pt;color:#64748b;border-top:1px solid #e2e8f0;padding-top:4px">'
                .RtlPdfDocument::e(config('app.name', 'اقساطي'))
                .' · Internal Credit Score · صفحة {PAGENO} من {nbpg}</div>'
            );
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

    private function reportQuery(User $user, string $report)
    {
        $query = $this->analyticsService->latestScoresQuery($user);

        match ($report) {
            'high_risk' => $query->whereIn('customer_credit_scores.risk_level', ['high', 'very_high']),
            'improving' => $query->where('customer_credit_scores.score_change', '>', 0),
            'declining' => $query->where('customer_credit_scores.score_change', '<', 0),
            'excellent_payment' => $query->where('customer_credit_scores.payment_history_score', '>=', 90),
            'serious_delinquency' => $query->where('customer_credit_scores.current_max_dpd', '>=', 90),
            'thin_file' => $query->where('customer_credit_scores.thin_file', true),
            'overdue' => $query->where('customer_credit_scores.current_overdue_count', '>', 0),
            default => null,
        };

        return $query;
    }

    private function riskLabelForLevel(?string $level): string
    {
        foreach ((array) config('credit_score.risk_bands', []) as $band) {
            if ($level === ($band['level'] ?? null)) {
                return (string) ($band['label'] ?? $level);
            }
        }

        return (string) $level;
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

    private function resolveCustomerSnapshot(Customer $customer): ?CustomerCreditScore
    {
        if (! $customer->installments()->exists()) {
            $this->creditScoreService->clearCurrentScore($customer);

            return null;
        }

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
     * @param  array<string, mixed>  $eligibility
     * @return array<string, mixed>
     */
    private function profilePayloadWithoutScore(array $eligibility): array
    {
        return [
            'score' => null,
            'score_applicable' => false,
            'score_max' => (int) config('credit_score.display.max', 850),
            'score_min' => (int) config('credit_score.display.min', 300),
            'installment_eligibility' => $eligibility,
            'disclaimer' => 'تقييم ائتماني داخلي — ليس I-Score رسميًا. لا يُعرض Score إلا بعد وجود أقساط مسجّلة.',
        ];
    }
}
