<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\InstallmentServiceInterface;
use App\Helpers\LimitsHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use App\Services\CreditScore\CreditScoreAnalyticsService;
use App\Services\CreditScore\CreditScoreInfrastructure;
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
