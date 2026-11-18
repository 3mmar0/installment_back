<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Customer;
use App\Models\Installment;
use App\Support\Pdf\RtlPdfDocument;
use App\Helpers\LimitsHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mpdf\Output\Destination;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CustomerStatementController extends Controller
{
    use ApiResponse;

    /**
     * Generate a PDF account statement for a customer.
     */
    public function pdf(int $customerId, Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $guard = $this->guardReports($request->user());
        if ($guard !== null) {
            return $guard;
        }

        $data = $request->validate([
            'installment_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'date_from'      => ['sometimes', 'nullable', 'date'],
            'date_to'        => ['sometimes', 'nullable', 'date'],
        ]);

        $customer = Customer::find($customerId);
        if (! $customer) {
            return $this->notFoundResponse('العميل غير موجود');
        }

        $this->authorize('view', $customer);

        $installments = $this->fetchInstallments($customer, $data);
        $html = $this->buildStatementHtml($customer, $installments, $data);

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

        $filename = 'statement-'.$customerId.'-'.date('Y-m-d-His').'.pdf';

        return response($binary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control'       => 'no-store, no-cache',
        ]);
    }

    /**
     * Generate an Excel account statement for a customer.
     */
    public function excel(int $customerId, Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $guard = $this->guardReports($request->user());
        if ($guard !== null) {
            return $guard;
        }

        $data = $request->validate([
            'installment_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'date_from'      => ['sometimes', 'nullable', 'date'],
            'date_to'        => ['sometimes', 'nullable', 'date'],
        ]);

        $customer = Customer::find($customerId);
        if (! $customer) {
            return $this->notFoundResponse('العميل غير موجود');
        }

        $this->authorize('view', $customer);

        $installments = $this->fetchInstallments($customer, $data);
        $spreadsheet  = $this->buildSpreadsheet($customer, $installments);

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($tmp === false) {
            return response()->json(['success' => false, 'message' => 'تعذر إنشاء ملف Excel'], 500);
        }

        try {
            (new Xlsx($spreadsheet))->save($tmp);
            $binary = file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }

        if ($binary === false) {
            return response()->json(['success' => false, 'message' => 'تعذر قراءة ملف Excel'], 500);
        }

        $filename = 'statement-'.$customerId.'-'.date('Y-m-d-His').'.xlsx';

        return response($binary, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control'       => 'no-store, no-cache',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function guardReports(?\App\Models\User $user): ?JsonResponse
    {
        if ($user === null) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 401);
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
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Collection<int, Installment>
     */
    private function fetchInstallments(Customer $customer, array $filters): \Illuminate\Database\Eloquent\Collection
    {
        $query = $customer->installments()->with(['items' => function ($q) use ($filters) {
            if (! empty($filters['date_from'])) {
                $q->where('due_date', '>=', $filters['date_from']);
            }
            if (! empty($filters['date_to'])) {
                $q->where('due_date', '<=', $filters['date_to']);
            }
        }])->orderBy('start_date');

        if (! empty($filters['installment_id'])) {
            $query->where('id', (int) $filters['installment_id']);
        }

        return $query->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Installment>  $installments
     * @param  array<string, mixed>  $filters
     */
    private function buildStatementHtml(Customer $customer, \Illuminate\Database\Eloquent\Collection $installments, array $filters): string
    {
        $e    = [RtlPdfDocument::class, 'e'];
        $now  = now()->format('Y-m-d H:i');

        // Totals
        $totalAmount    = 0.0;
        $paidAmount     = 0.0;
        $pendingAmount  = 0.0;
        $overdueAmount  = 0.0;
        $today          = now()->startOfDay();

        foreach ($installments as $inst) {
            foreach ($inst->items as $item) {
                $totalAmount += (float) $item->amount;
                if ($item->status === 'paid') {
                    $paidAmount += (float) ($item->paid_amount ?: $item->amount);
                } else {
                    $due = \Carbon\Carbon::parse($item->due_date)->startOfDay();
                    if ($due->lt($today)) {
                        $overdueAmount += (float) $item->amount;
                    } else {
                        $pendingAmount += (float) $item->amount;
                    }
                }
            }
        }
        $remaining = $totalAmount - $paidAmount;

        // Subtitle
        $filterNote = '';
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $from = $filters['date_from'] ?? '—';
            $to   = $filters['date_to']   ?? '—';
            $filterNote = "من {$from} إلى {$to}";
        }

        $subtitle = "تاريخ الإصدار: {$now}" . ($filterNote ? " · نطاق: {$filterNote}" : '');

        // Summary cards
        $summary = <<<HTML
<table class="card-grid">
  <tr>
    <td><div class="k">إجمالي المبلغ</div><div class="v">{$this->fmt($totalAmount)}</div></td>
    <td><div class="k">المدفوع</div><div class="v" style="color:#166534">{$this->fmt($paidAmount)}</div></td>
    <td><div class="k">المتبقي</div><div class="v" style="color:#9a3412">{$this->fmt($remaining)}</div></td>
  </tr>
  <tr>
    <td><div class="k">قيد الانتظار</div><div class="v">{$this->fmt($pendingAmount)}</div></td>
    <td><div class="k">متأخر</div><div class="v" style="color:#991b1b">{$this->fmt($overdueAmount)}</div></td>
    <td><div class="k">عدد الأقساط</div><div class="v">{$installments->count()}</div></td>
  </tr>
</table>
HTML;

        // Customer info
        $infoRows = '';
        $fields = [
            'الاسم'          => $customer->name,
            'الهاتف'         => $customer->phone,
            'البريد'         => $customer->email,
            'الرقم القومي'   => $customer->national_id,
            'المحافظة'       => $customer->governorate,
            'العنوان'        => $customer->address,
            'الوظيفة'        => $customer->job,
            'الملاحظات'      => $customer->notes,
        ];
        foreach ($fields as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $infoRows .= '<tr><td class="label"><strong>' . $e($label) . '</strong></td><td>' . $e($value) . '</td></tr>';
        }

        $customerSection = <<<HTML
<h2 class="section-title">بيانات العميل</h2>
<table class="data summary">
  <tbody>{$infoRows}</tbody>
</table>
HTML;

        // Installments + items
        $installmentsSection = '<h2 class="section-title">تفاصيل حركات السداد</h2>';

        if ($installments->isEmpty()) {
            $installmentsSection .= '<p style="color:#64748b;text-align:center;padding:16px">لا توجد بيانات بالفترة المحددة</p>';
        } else {
            foreach ($installments as $inst) {
                $instName = $e($inst->name ?: ('قسط #' . $inst->id));
                $instMeta = $e(
                    'من ' . \Carbon\Carbon::parse($inst->start_date)->format('Y-m-d') .
                    ' · ' . $inst->months . ' شهر' .
                    ' · الإجمالي: ' . $this->fmt((float) $inst->total_amount)
                );

                $rows = '';
                $itemNum = 1;
                foreach ($inst->items as $item) {
                    $due      = \Carbon\Carbon::parse($item->due_date);
                    $isOver   = $item->status !== 'paid' && $due->startOfDay()->lt($today);
                    $statusAr = match ($item->status) {
                        'paid'    => 'مدفوع ✓',
                        'partial' => 'جزئي',
                        'overdue' => 'متأخر',
                        default   => $isOver ? 'متأخر' : 'معلق',
                    };
                    $rowColor = $item->status === 'paid'
                        ? 'background:#f0fdf4'
                        : ($isOver ? 'background:#fef2f2' : 'background:#fffbeb');

                    $paidAtText = $item->paid_at
                        ? \Carbon\Carbon::parse($item->paid_at)->format('Y-m-d')
                        : '—';

                    $rows .= <<<TR
<tr style="{$rowColor}">
  <td style="text-align:center">{$itemNum}</td>
  <td>{$e($due->format('Y-m-d'))}</td>
  <td class="ltr">{$this->fmt((float)$item->amount)}</td>
  <td class="ltr">{$this->fmt((float)($item->paid_amount ?? 0))}</td>
  <td>{$e($paidAtText)}</td>
  <td>{$e($item->reference ?? '—')}</td>
  <td><strong>{$e($statusAr)}</strong></td>
</tr>
TR;
                    $itemNum++;
                }

                if ($rows === '') {
                    $rows = '<tr><td colspan="7" style="text-align:center;color:#64748b">لا توجد دفعات</td></tr>';
                }

                $installmentsSection .= <<<BLOCK
<div style="margin-bottom:16px">
  <div style="background:#eff6ff;border-right:4px solid #2563eb;padding:8px 12px;border-radius:0 6px 6px 0;margin-bottom:6px">
    <strong style="font-size:11pt">{$instName}</strong>
    <span style="font-size:9pt;color:#64748b;margin-right:8px">{$instMeta}</span>
  </div>
  <table class="data">
    <thead>
      <tr>
        <th>#</th>
        <th>تاريخ الاستحقاق</th>
        <th>المبلغ</th>
        <th>المدفوع</th>
        <th>تاريخ السداد</th>
        <th>المرجع</th>
        <th>الحالة</th>
      </tr>
    </thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
BLOCK;
            }
        }

        $body = $summary . $customerSection . $installmentsSection
            . '<p class="footer-note">كشف حساب داخلي من نظام «اقساطي» · للاستخدام التشغيلي فقط.</p>';

        return RtlPdfDocument::documentShell('كشف حساب العميل', $subtitle, $body);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Installment>  $installments
     */
    private function buildSpreadsheet(Customer $customer, \Illuminate\Database\Eloquent\Collection $installments): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        // Sheet 1: summary
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ملخص');

        $sheet->setCellValue('A1', 'بيانات العميل');
        $row = 2;
        foreach ([
            'الاسم'        => $customer->name,
            'الهاتف'       => $customer->phone,
            'البريد'       => $customer->email,
            'الرقم القومي' => $customer->national_id,
            'المحافظة'     => $customer->governorate,
        ] as $label => $val) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $val ?? '');
            $row++;
        }

        // Sheet 2+: one per installment (or all combined)
        $today = now()->startOfDay();
        foreach ($installments as $inst) {
            $title     = mb_substr(preg_replace('/[\/*?:\[\]]/', '-', $inst->name ?: 'قسط-'.$inst->id) ?: 'قسط', 0, 31);
            $instSheet = $spreadsheet->createSheet();
            $instSheet->setTitle($title);

            $headers = ['#', 'تاريخ الاستحقاق', 'المبلغ', 'المدفوع', 'تاريخ السداد', 'المرجع', 'الحالة'];
            $col     = 1;
            foreach ($headers as $h) {
                $instSheet->setCellValue(Coordinate::stringFromColumnIndex($col).'1', $h);
                $col++;
            }

            $rowNum = 2;
            $itemNum = 1;
            foreach ($inst->items as $item) {
                $due    = \Carbon\Carbon::parse($item->due_date);
                $isOver = $item->status !== 'paid' && $due->startOfDay()->lt($today);
                $status = match ($item->status) {
                    'paid'    => 'مدفوع',
                    'partial' => 'جزئي',
                    'overdue' => 'متأخر',
                    default   => $isOver ? 'متأخر' : 'معلق',
                };

                $instSheet->setCellValue('A'.$rowNum, $itemNum);
                $instSheet->setCellValue('B'.$rowNum, $due->format('Y-m-d'));
                $instSheet->setCellValue('C'.$rowNum, (float) $item->amount);
                $instSheet->setCellValue('D'.$rowNum, (float) ($item->paid_amount ?? 0));
                $instSheet->setCellValue('E'.$rowNum, $item->paid_at ? \Carbon\Carbon::parse($item->paid_at)->format('Y-m-d') : '');
                $instSheet->setCellValue('F'.$rowNum, $item->reference ?? '');
                $instSheet->setCellValue('G'.$rowNum, $status);

                $rowNum++;
                $itemNum++;
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function fmt(float $amount): string
    {
        return number_format($amount, 2, '.', ',').' ج.م';
    }
}
