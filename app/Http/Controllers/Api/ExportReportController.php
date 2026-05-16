<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\InstallmentServiceInterface;
use App\Helpers\LimitsHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportReportController extends Controller
{
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

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'dashboard-report-'.date('Y-m-d-His').'.pdf';

        return response($dompdf->output(), 200, [
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
            ['مستحق قريباً (7 أيام)', $this->scalarValue($analytics['dueSoon'] ?? '')],
            ['متأخر', $this->scalarValue($analytics['overdue'] ?? '')],
            ['إجمالي المبالغ المستحقة', $this->scalarValue($analytics['outstanding'] ?? '')],
            ['المتحصل هذا الشهر', $this->scalarValue($analytics['collectedThisMonth'] ?? '')],
            ['إجمالي الأقساط', $this->scalarValue($analytics['totalInstallments'] ?? '')],
            ['أقساط نشطة', $this->scalarValue($analytics['activeInstallments'] ?? '')],
            ['أقساط مكتملة', $this->scalarValue($analytics['completedInstallments'] ?? '')],
            ['إجمالي العملاء', $this->scalarValue($analytics['totalCustomers'] ?? '')],
            ['عملاء نشطون', $this->scalarValue($analytics['activeCustomers'] ?? '')],
            ['متحصل الشهر الماضي', $this->scalarValue($analytics['collectedLastMonth'] ?? '')],
            ['نمو التحصيل %', $this->scalarValue($analytics['collectionGrowth'] ?? '')],
        ];
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

    /**
     * @param  array<string, mixed>  $analytics
     */
    private function buildDashboardHtml(array $analytics): string
    {
        $e = fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $table = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:11px"><tbody>';
        foreach ($this->summaryPairs($analytics) as [$label, $value]) {
            $table .= '<tr><td>'.$e($label).'</td><td>'.$e($value).'</td></tr>';
        }
        $table .= '</tbody></table>';

        return '<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>تقرير لوحة التحكم</title></head><body style="font-family:DejaVu Sans,sans-serif">'
            .'<h2 style="text-align:center">تقرير لوحة التحكم</h2>'
            .'<p style="text-align:center;color:#444">'.$e(now()->toDateTimeString()).'</p>'
            .$table
            .$this->buildPaymentsTableHtml('الدفعات القادمة', $analytics['upcoming'] ?? [], ['customer_name', 'due_date', 'amount', 'days_until_due'], $e)
            .$this->buildPaymentsTableHtml('الدفعات المتأخرة', $analytics['overduePayments'] ?? [], ['customer_name', 'due_date', 'amount', 'days_overdue'], $e)
            .'</body></html>';
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

        $h = '<h3 style="margin-top:16px">'.$e($title).'</h3><table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:10px"><thead><tr>';
        foreach ($columns as $c) {
            $h .= '<th>'.$e($c).'</th>';
        }
        $h .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $h .= '<tr>';
            foreach ($columns as $c) {
                $h .= '<td>'.$e($this->scalarValue($row[$c] ?? '')).'</td>';
            }
            $h .= '</tr>';
        }

        return $h.'</tbody></table>';
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
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).'1', $header);
            $col++;
        }

        $rowNum = 2;
        foreach ($rows as $row) {
            $col = 1;
            foreach ($columns as $key) {
                $val = $this->scalarValue($row[$key] ?? '');
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
        fputcsv($handle, $columns, ',', '"', '\\');
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $c) {
                $line[] = $this->scalarValue($row[$c] ?? '');
            }
            fputcsv($handle, $line, ',', '"', '\\');
        }
    }
}
