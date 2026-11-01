<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\InstallmentServiceInterface;
use App\Helpers\LimitsHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use App\Services\CreditScore\CreditScoreAnalyticsService;
use App\Services\CreditScore\CreditScoreInfrastructure;
use App\Services\Reports\ReportCatalog;
use App\Services\Reports\ReportQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsHubController extends Controller
{
    use ApiResponse;

    /** @var array<string, array{key: string, title: string, description: string}> */
    public const CREDIT_REPORT_TYPES = [
        'high_risk' => [
            'key' => 'high_risk',
            'title' => 'عملاء مخاطرة عالية',
            'description' => 'درجة مخاطرة high أو very_high',
        ],
        'overdue' => [
            'key' => 'overdue',
            'title' => 'عملاء لديهم متأخرات',
            'description' => 'أقساط متأخرة حاليًا',
        ],
        'serious_delinquency' => [
            'key' => 'serious_delinquency',
            'title' => 'تأخير شديد (+90 يوم)',
            'description' => 'أقصى DPD 90 يومًا أو أكثر',
        ],
        'declining' => [
            'key' => 'declining',
            'title' => 'درجات في تراجع',
            'description' => 'آخر إعادة حساب أظهرت انخفاضًا',
        ],
        'improving' => [
            'key' => 'improving',
            'title' => 'درجات في تحسّن',
            'description' => 'آخر إعادة حساب أظهرت ارتفاعًا',
        ],
        'excellent_payment' => [
            'key' => 'excellent_payment',
            'title' => 'سداد ممتاز',
            'description' => 'مكون سجل السداد ≥ 90',
        ],
        'thin_file' => [
            'key' => 'thin_file',
            'title' => 'ملفات رقيقة',
            'description' => 'تاريخ محدود — درجة ممزوجة مع خط أساس',
        ],
    ];

    public function __construct(
        private readonly InstallmentServiceInterface $installmentService,
        private readonly CreditScoreAnalyticsService $creditAnalytics,
        private readonly ReportQueryService $reportQueryService,
    ) {
    }

    public function catalog(Request $request): JsonResponse
    {
        $user = $request->user();
        $financial = $this->canAccessFinancialReports($user);
        $credit = $this->canAccessCreditReports($user);

        return $this->successResponse([
            'financial_enabled' => $financial,
            'credit_enabled' => $credit,
            'financial_exports' => $financial ? [
                ['format' => 'pdf', 'label' => 'PDF — ملخص لوحة التحكم'],
                ['format' => 'excel', 'label' => 'Excel — ملخص + جداول'],
                ['format' => 'csv', 'label' => 'CSV — ملخص + جداول'],
            ] : [],
            'categories' => $financial ? ReportCatalog::definitions() : [],
            'credit_report_types' => $credit ? array_values(self::CREDIT_REPORT_TYPES) : [],
        ], 'تم جلب فهرس التقارير');
    }

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $payload = [
            'financial' => null,
            'credit' => null,
        ];

        if ($this->canAccessFinancialReports($user)) {
            $payload['financial'] = $this->installmentService->getDashboardAnalytics($user);
        }

        if ($this->canAccessCreditReports($user) && CreditScoreInfrastructure::schemaReady()) {
            $credit = $this->creditAnalytics->dashboard($user);
            $credit['score_trend'] = $this->creditAnalytics->scoreTrend($user, 30);
            $payload['credit'] = $credit;
        }

        return $this->successResponse($payload, 'تم جلب ملخص التقارير');
    }

    public function show(Request $request, string $reportKey): JsonResponse
    {
        if (! ReportCatalog::has($reportKey)) {
            return $this->notFoundResponse('التقرير غير موجود');
        }

        $user = $request->user();
        if (! $this->canAccessFinancialReports($user)) {
            return $this->forbiddenResponse('التقارير المالية غير مفعّلة في خطتك');
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ]);

        $data = $this->reportQueryService->run($user, $reportKey, $validated);

        return $this->successResponse($data, 'تم جلب التقرير');
    }

    public function export(Request $request, string $reportKey): JsonResponse|\Illuminate\Http\Response
    {
        if (! ReportCatalog::has($reportKey)) {
            return $this->notFoundResponse('التقرير غير موجود');
        }

        $user = $request->user();
        if (! $this->canAccessFinancialReports($user)) {
            return $this->forbiddenResponse('التقارير المالية غير مفعّلة في خطتك');
        }

        $validated = $request->validate([
            'format' => ['required', 'string', 'in:csv,excel,pdf'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ]);

        if ($validated['format'] !== 'csv') {
            return $this->errorResponse('صيغة التصدير غير متاحة لهذا التقرير بعد', 422);
        }

        $payload = $this->reportQueryService->run($user, $reportKey, [
            'page' => 1,
            'per_page' => 100,
            'days' => $validated['days'] ?? 14,
        ]);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return $this->errorResponse('تعذر إنشاء ملف التصدير', 500);
        }

        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, array_map(fn (array $column) => $column['label'], $payload['columns']), ',', '"', '\\');
        foreach ($payload['rows'] as $row) {
            $line = [];
            foreach ($payload['columns'] as $column) {
                $line[] = $row[$column['key']] ?? '';
            }
            fputcsv($handle, $line, ',', '"', '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false) {
            return $this->errorResponse('تعذر إنشاء ملف التصدير', 500);
        }

        $filename = "report-{$reportKey}-".date('Y-m-d-His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function canAccessFinancialReports(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->isOwner()) {
            return true;
        }
        $limit = LimitsHelper::getUserLimits($user->id);

        return $limit !== null && $limit->reports;
    }

    private function canAccessCreditReports(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->isOwner()) {
            return true;
        }
        $limit = LimitsHelper::getUserLimits($user->id);
        if ($limit === null) {
            return false;
        }

        return $limit->canAccessFeature('internal_credit_score');
    }
}
