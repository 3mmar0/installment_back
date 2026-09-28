<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\InstallmentServiceInterface;
use App\Helpers\LimitsHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\Pdf\RtlPdfDocument;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportReportController extends Controller
{
    /** @var array<string, string> */
    private const COLUMN_LABELS = [
        'customer_name' => 'العميل',
        'due_date' => 'تاريخ الاستحقاق',
        'amount' => 'المبلغ',
        'days_until_due' => 'أيام متبقية',
        'days_overdue' => 'أيام التأخير',
        'paid_at' => 'تاريخ الدفع',
        'paid_amount' => 'المبلغ المدفوع',
        'reference' => 'المرجع',
    ];

    public function __construct(
        private readonly InstallmentServiceInterface $installmentService
    ) {}

    public function pdf(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $guard = $this->guardReports($request->user());
        if ($guard !== null) {
            return $guard;
        }

        $request->validate([
            'scope' => ['sometimes', 'string', 'in:dashboard'],
        ]);

        $analytics = $this->normalizeAnalytics(
            $this->installmentService->getDashboardAnalytics($request->user())
        );

        $html = $this->buildDashboardHtml($analytics);

        try {
            $mpdf = RtlPdfDocument::createMpdf();
            $mpdf->SetHTMLFooter(
                '<div style="text-align:center;font-size:8pt;color:#64748b">صفحة {PAGENO} من {nbpg}</div>'
            );
            $mpdf->WriteHTML($html);
            $binary = $mpdf->Output('', Destination::STRING_RETURN);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'تعذر إنشاء ملف PDF',
            ], 500);
        }

        $filename = 'dashboard-report-'.date('Y-m-d-His').'.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function excel(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $guard = $this->guardReports($request->user());
        if ($guard !== null) {
            return $guard;
        }

        $request->validate([
            'scope' => ['sometimes', 'string', 'in:dashboard'],
        ]);

        $analytics = $this->normalizeAnalytics(
            $this->installmentService->getDashboardAnalytics($request->user())
        );

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr('ملخص', 0, 31));

        $sheet->setCellValue('A1', 'المؤشر');
        $sheet->setCellValue('B1', 'القيمة');
        $row = 2;
        foreach ($this->summaryPairs($analytics) as [$label, $value]) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $value);
            $row++;
        }

        $this->appendSheetFromRows(
            $spreadsheet,
            'قريب الاستحقاق',
            $analytics['upcoming'] ?? [],
            ['customer_name', 'due_date', 'amount', 'days_until_due']
        );
        $this->appendSheetFromRows(
            $spreadsheet,
            'متأخر',
            $analytics['overduePayments'] ?? [],
            ['customer_name', 'due_date', 'amount', 'days_overdue']
        );
        $this->appendSheetFromRows(
            $spreadsheet,
            'دفعات حديثة',
            $analytics['recentPayments'] ?? [],
            ['customer_name', 'paid_at', 'paid_amount', 'reference']
        );

        $spreadsheet->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($tmp === false) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر إنشاء ملف Excel',
            ], 500);
        }
        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($tmp);
            $binary = file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }

        if ($binary === false) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر قراءة ملف Excel',
            ], 500);
        }

        $filename = 'dashboard-report-'.date('Y-m-d-His').'.xlsx';

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function csv(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $guard = $this->guardReports($request->user());
        if ($guard !== null) {
            return $guard;
        }

        $request->validate([
            'scope' => ['sometimes', 'string', 'in:dashboard'],
        ]);

        $analytics = $this->normalizeAnalytics(
            $this->installmentService->getDashboardAnalytics($request->user())
        );

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر إنشاء ملف CSV',
            ], 500);
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['المؤشر', 'القيمة'], ',', '"', '\\');
        foreach ($this->summaryPairs($analytics) as [$label, $value]) {
            fputcsv($handle, [$label, $value], ',', '"', '\\');
        }

        fputcsv($handle, [], ',', '"', '\\');
        fputcsv($handle, ['الدفعات القادمة'], ',', '"', '\\');
        $this->fputcsvRows($handle, $analytics['upcoming'] ?? [], ['customer_name', 'due_date', 'amount', 'days_until_due']);

        fputcsv($handle, [], ',', '"', '\\');
        fputcsv($handle, ['الدفعات المتأخرة'], ',', '"', '\\');
        $this->fputcsvRows($handle, $analytics['overduePayments'] ?? [], ['customer_name', 'due_date', 'amount', 'days_overdue']);

        fputcsv($handle, [], ',', '"', '\\');
        fputcsv($handle, ['الدفعات الحديثة'], ',', '"', '\\');
        $this->fputcsvRows($handle, $analytics['recentPayments'] ?? [], ['customer_name', 'paid_at', 'paid_amount', 'reference']);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر قراءة ملف CSV',
            ], 500);
        }

        $filename = 'dashboard-report-'.date('Y-m-d-His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function guardReports(?User $user): ?JsonResponse
    {
        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح',
            ], 401);
        }

        if ($user->isOwner()) {
            return null;
        }

        $limit = LimitsHelper::getUserLimits($user->id);
        if ($limit === null || ! $limit->reports) {
            return response()->json([
                'success' => false,
                'message' => LimitsHelper::getLimitExceededMessage('reports'),
            ], 403);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return array<string, mixed>
     */
    private function normalizeAnalytics(array $analytics): array
    {
        foreach (['upcoming', 'overduePayments', 'recentPayments', 'topCustomers', 'monthlyTrend'] as $key) {
            if (! isset($analytics[$key])) {
                continue;
            }
            $val = $analytics[$key];
            if (is_object($val) && method_exists($val, 'toArray')) {
                $analytics[$key] = $val->toArray();
            }
        }

        return $analytics;
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return list<array{0: string, 1: float|int|string}>
     */
    private function summaryPairs(array $analytics): array
    {
        return [
            ['مستحق قريباً (7 أيام)', $this->formatExportValue($analytics['dueSoon'] ?? '')],
            ['متأخر', $this->formatExportValue($analytics['overdue'] ?? '')],
            ['إجمالي المبالغ المستحقة', $this->formatExportValue($analytics['outstanding'] ?? '', true)],
            ['المتحصل هذا الشهر', $this->formatExportValue($analytics['collectedThisMonth'] ?? '', true)],
            ['إجمالي الأقساط', $this->formatExportValue($analytics['totalInstallments'] ?? '')],
            ['أقساط نشطة', $this->formatExportValue($analytics['activeInstallments'] ?? '')],
            ['أقساط مكتملة', $this->formatExportValue($analytics['completedInstallments'] ?? '')],
            ['إجمالي العملاء', $this->formatExportValue($analytics['totalCustomers'] ?? '')],
            ['عملاء نشطون', $this->formatExportValue($analytics['activeCustomers'] ?? '')],
            ['متحصل الشهر الماضي', $this->formatExportValue($analytics['collectedLastMonth'] ?? '', true)],
            ['نمو التحصيل %', $this->formatExportValue($analytics['collectionGrowth'] ?? '')],
        ];
    }

    private function formatExportValue(mixed $value, bool $asMoney = false): float|int|string
    {
        $scalar = $this->scalarValue($value);
        if ($asMoney && is_numeric($scalar)) {
            return number_format((float) $scalar, 2, '.', ',').' ج.م';
        }

        return $scalar;
    }

    private function scalarValue(mixed $value): float|int|string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        return is_scalar($value) ? $value : '';
    }

    private function columnLabel(string $key): string
    {
        return self::COLUMN_LABELS[$key] ?? $key;
    }

    /**
     * @param  array<string, mixed>  $analytics
     */
    private function buildDashboardHtml(array $analytics): string
    {
        $e = fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $generatedAt = $e(now()->format('Y-m-d H:i'));

        $summaryRows = '';
        foreach ($this->summaryPairs($analytics) as [$label, $value]) {
            $summaryRows .= '<tr><td class="label">'.$e($label).'</td><td class="value">'.$e($value).'</td></tr>';
        }

        $sections = $this->buildPaymentsTableHtml('الدفعات القادمة', $analytics['upcoming'] ?? [], ['customer_name', 'due_date', 'amount', 'days_until_due'], $e)
            .$this->buildPaymentsTableHtml('الدفعات المتأخرة', $analytics['overduePayments'] ?? [], ['customer_name', 'due_date', 'amount', 'days_overdue'], $e)
            .$this->buildPaymentsTableHtml('الدفعات الحديثة', $analytics['recentPayments'] ?? [], ['customer_name', 'paid_at', 'paid_amount', 'reference'], $e);

        $styles = RtlPdfDocument::baseStyles();

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تقرير لوحة التحكم</title>
<style>
{$styles}
</style>
</head>
<body>
  <div class="doc-header">
    <h1>تقرير لوحة التحكم المالي</h1>
    <div class="meta">تاريخ التصدير: {$generatedAt} · نطاق: ملخص المحفظة والدفعات</div>
  </div>
  <h2 class="section-title">ملخص المؤشرات</h2>
  <table class="data summary">
    <thead><tr><th>المؤشر</th><th>القيمة</th></tr></thead>
    <tbody>{$summaryRows}</tbody>
  </table>
  {$sections}
  <p class="footer-note">تقرير داخلي من بيانات «اقساطي» — للاستخدام التشغيلي فقط.</p>
</body>
</html>
HTML;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    private function buildPaymentsTableHtml(string $title, array $rows, array $columns, callable $e): string
    {
        if ($rows === []) {
            return '';
        }

        $headers = '';
        foreach ($columns as $c) {
            $headers .= '<th>'.$e($this->columnLabel($c)).'</th>';
        }

        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>';
            foreach ($columns as $c) {
                $cell = $this->formatCellValue($c, $row[$c] ?? '');
                $class = in_array($c, ['amount', 'paid_amount'], true) ? ' class="ltr"' : '';
                $body .= '<td'.$class.'>'.$e($cell).'</td>';
            }
            $body .= '</tr>';
        }

        return '<h2 class="section-title">'.$e($title).'</h2>'
            .'<table class="data"><thead><tr>'.$headers.'</tr></thead><tbody>'.$body.'</tbody></table>';
    }

    private function formatCellValue(string $column, mixed $value): string
    {
        if (in_array($column, ['amount', 'paid_amount'], true) && is_numeric($value)) {
            return number_format((float) $value, 2, '.', ',').' ج.م';
        }

        return (string) $this->scalarValue($value);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    private function appendSheetFromRows(Spreadsheet $spreadsheet, string $title, array $rows, array $columns): void
    {
        if ($rows === []) {
            return;
        }

        $sheet = $spreadsheet->createSheet();
        $safeTitle = preg_replace('/[\/*?:\[\]]/', '-', $title) ?: 'Sheet';
        $sheet->setTitle(mb_substr($safeTitle, 0, 31));

        $col = 1;
        foreach ($columns as $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).'1', $this->columnLabel($header));
            $col++;
        }

        $rowNum = 2;
        foreach ($rows as $row) {
            $col = 1;
            foreach ($columns as $key) {
                $val = $this->formatCellValue($key, $row[$key] ?? '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).$rowNum, $val);
                $col++;
            }
            $rowNum++;
        }
    }

    /**
     * @param  resource  $handle
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    private function fputcsvRows(mixed $handle, array $rows, array $columns): void
    {
        fputcsv($handle, array_map(fn (string $c) => $this->columnLabel($c), $columns), ',', '"', '\\');
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $c) {
                $line[] = $this->formatCellValue($c, $row[$c] ?? '');
            }
            fputcsv($handle, $line, ',', '"', '\\');
        }
    }
}
