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

        $customer = Customer::with('user')->find($customerId);
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
        $e     = [RtlPdfDocument::class, 'e'];
        $now   = now()->format('Y-m-d H:i');
        $today = now()->startOfDay();

        /** @var \App\Models\User|null $vendor */
        $vendor         = $customer->user ?? null;
        $vendorName     = $vendor?->name ?? '';
        $vendorPhone    = $vendor?->phone ?? '';
        $vendorLogoPath = $vendor?->logo ?? null;

        // ── Overall totals ───────────────────────────────────────────────────
        $totalAmount   = 0.0;
        $paidAmount    = 0.0;
        $pendingAmount = 0.0;
        $overdueAmount = 0.0;

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

        // ── Subtitle ─────────────────────────────────────────────────────────
        $filterNote = '';
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $from       = $filters['date_from'] ?? '—';
            $to         = $filters['date_to']   ?? '—';
            $filterNote = "نطاق: {$from} إلى {$to}";
        }
        $subtitle = "تاريخ الإصدار: {$now}" . ($filterNote ? " · {$filterNote}" : '');

        // ── Vendor bar ───────────────────────────────────────────────────────
        $logoHtml = '';
        if ($vendorLogoPath) {
            $logoAbsPath = storage_path('app/public/' . ltrim($vendorLogoPath, '/'));
            if (file_exists($logoAbsPath)) {
                $ext      = strtolower(pathinfo($logoAbsPath, PATHINFO_EXTENSION));
                $mime     = match ($ext) { 'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg' };
                $b64      = base64_encode((string) file_get_contents($logoAbsPath));
                $logoHtml = "<img src=\"data:{$mime};base64,{$b64}\" style=\"height:52px;max-width:130px;object-fit:contain;vertical-align:middle\" />";
            }
        }

        $vendorBar = '';
        if ($vendorName || $vendorPhone || $logoHtml) {
            $nameHtml  = $vendorName  ? '<div class="vendor-name">' . $e($vendorName) . '</div>' : '';
            $phoneHtml = $vendorPhone ? '<div class="vendor-phone">&#128222; ' . $e($vendorPhone) . '</div>' : '';
            $logoCell  = $logoHtml ? "<td style=\"text-align:left;width:140px;vertical-align:middle\">{$logoHtml}</td>" : '';
            $vendorBar = <<<HTML
<div class="vendor-bar">
  <table style="width:100%;border-collapse:collapse">
    <tr>
      <td style="text-align:right;vertical-align:middle">{$nameHtml}{$phoneHtml}</td>
      {$logoCell}
    </tr>
  </table>
</div>
HTML;
        }

        // ── Overall summary cards ────────────────────────────────────────────
        $instCount = $installments->count();
        $summary   = <<<HTML
<h2 class="section-title">الملخص الإجمالي</h2>
<table class="card-grid">
  <tr>
    <td><div class="k">إجمالي المبلغ</div><div class="v">{$this->fmt($totalAmount)}</div></td>
    <td><div class="k">المدفوع</div><div class="v paid">{$this->fmt($paidAmount)}</div></td>
    <td><div class="k">المتبقي</div><div class="v remaining">{$this->fmt($remaining)}</div></td>
  </tr>
  <tr>
    <td><div class="k">قيد الانتظار</div><div class="v pending">{$this->fmt($pendingAmount)}</div></td>
    <td><div class="k">متأخر</div><div class="v overdue">{$this->fmt($overdueAmount)}</div></td>
    <td><div class="k">عدد الأقساط</div><div class="v">{$instCount}</div></td>
  </tr>
</table>
HTML;

        // ── Customer info (2-column card grid) ───────────────────────────────
        $fields = [
            'الاسم'             => $customer->name,
            'الهاتف'            => $customer->phone,
            'البريد الإلكتروني' => $customer->email,
            'الرقم القومي'      => $customer->national_id,
            'المحافظة'          => $customer->governorate,
            'العنوان'           => $customer->address,
            'الوظيفة'           => $customer->job,
            'الملاحظات'         => $customer->notes,
        ];

        $infoCells = '';
        $col       = 0;
        foreach ($fields as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($col > 0 && $col % 2 === 0) {
                $infoCells .= '</tr><tr>';
            }
            $infoCells .= '<td class="info-cell"><div class="info-label">' . $e($label) . '</div><div class="info-value">' . $e($value) . '</div></td>';
            $col++;
        }
        // pad to even columns
        if ($col % 2 === 1) {
            $infoCells .= '<td class="info-cell" style="background:transparent;border:none"></td>';
        }

        $customerSection = <<<HTML
<h2 class="section-title">بيانات العميل</h2>
<table class="info-grid">
  <tbody><tr>{$infoCells}</tr></tbody>
</table>
HTML;

        // ── Installments (each on its own page when multiple) ────────────────
        $installmentsHtml = '';

        if ($installments->isEmpty()) {
            $installmentsHtml = '<p style="color:#64748b;text-align:center;padding:20px">لا توجد بيانات بالفترة المحددة</p>';
        } else {
            $isFirst = true;
            foreach ($installments as $inst) {
                $pageBreakStyle = $isFirst ? '' : 'page-break-before:always;';
                $isFirst        = false;

                $instName  = $e($inst->name ?: ('قسط #' . $inst->id));
                $startFmt  = \Carbon\Carbon::parse($inst->start_date)->format('Y-m-d');
                $endFmt    = \Carbon\Carbon::parse($inst->end_date)->format('Y-m-d');
                $instTotal = (float) $inst->total_amount;

                // Per-installment buckets
                $instPaid    = 0.0;
                $instOverdue = 0.0;
                $instPending = 0.0;
                $paidItems    = [];
                $overdueItems = [];
                $pendingItems = [];

                foreach ($inst->items as $item) {
                    $due    = \Carbon\Carbon::parse($item->due_date)->startOfDay();
                    $isOver = $item->status !== 'paid' && $due->lt($today);

                    if ($item->status === 'paid') {
                        $instPaid += (float) ($item->paid_amount ?: $item->amount);
                        $paidItems[] = $item;
                    } elseif ($item->status === 'overdue' || $isOver) {
                        $instOverdue += (float) $item->amount;
                        $overdueItems[] = $item;
                    } else {
                        $instPending += (float) $item->amount;
                        $pendingItems[] = $item;
                    }
                }

                $instRemaining = $instTotal - $instPaid;
                $pct           = $instTotal > 0 ? round(($instPaid / $instTotal) * 100) : 0;
                $barWidth      = min(100, max(0, $pct));

                // Status badge
                [$statusAr, $statusColor, $statusBg, $barColor] = match ($inst->status) {
                    'active'    => ['نشط',    '#1d4ed8', '#eff6ff', '#2563eb'],
                    'completed' => ['مكتمل',  '#166534', '#f0fdf4', '#16a34a'],
                    'overdue'   => ['متأخر',  '#991b1b', '#fef2f2', '#dc2626'],
                    'cancelled' => ['ملغي',   '#475569', '#f1f5f9', '#94a3b8'],
                    default     => ['—',       '#475569', '#f8fafc', '#94a3b8'],
                };

                // Build table rows helper
                $buildRow = function (object $item, int $num) use ($e, $today): string {
                    $due        = \Carbon\Carbon::parse($item->due_date);
                    $isOver     = $item->status !== 'paid' && $due->startOfDay()->lt($today);
                    $statusText = match ($item->status) {
                        'paid'    => '&#10003; مدفوع',
                        'partial' => '&#9681; جزئي',
                        'overdue' => '&#10007; متأخر',
                        default   => $isOver ? '&#10007; متأخر' : '&#9203; معلق',
                    };
                    $paidAt = $item->paid_at ? \Carbon\Carbon::parse($item->paid_at)->format('Y-m-d') : '—';

                    return <<<TR
<tr>
  <td style="text-align:center;width:28px">{$num}</td>
  <td>{$e($due->format('Y-m-d'))}</td>
  <td class="ltr">{$this->fmt((float)$item->amount)}</td>
  <td class="ltr">{$this->fmt((float)($item->paid_amount ?? 0))}</td>
  <td>{$e($paidAt)}</td>
  <td>{$e($item->reference ?? '—')}</td>
  <td style="text-align:center"><strong>{$statusText}</strong></td>
</tr>
TR;
                };

                // Grouped table body
                $tbody  = '';
                $serial = 1;

                if (! empty($paidItems)) {
                    $cnt = count($paidItems);
                    $tbody .= "<tr class=\"group-hdr group-paid\"><td colspan=\"7\">&#10003; مدفوعة &mdash; {$cnt} " . ($cnt === 1 ? 'دفعة' : 'دفعات') . " &middot; {$this->fmt($instPaid)}</td></tr>";
                    foreach ($paidItems as $item) {
                        $tbody .= str_replace('<tr>', '<tr style="background:#f0fdf4">', $buildRow($item, $serial++));
                    }
                }
                if (! empty($overdueItems)) {
                    $cnt = count($overdueItems);
                    $tbody .= "<tr class=\"group-hdr group-overdue\"><td colspan=\"7\">&#10007; متأخرة &mdash; {$cnt} " . ($cnt === 1 ? 'دفعة' : 'دفعات') . " &middot; {$this->fmt($instOverdue)}</td></tr>";
                    foreach ($overdueItems as $item) {
                        $tbody .= str_replace('<tr>', '<tr style="background:#fef2f2">', $buildRow($item, $serial++));
                    }
                }
                if (! empty($pendingItems)) {
                    $cnt = count($pendingItems);
                    $tbody .= "<tr class=\"group-hdr group-pending\"><td colspan=\"7\">&#9203; قادمة &mdash; {$cnt} " . ($cnt === 1 ? 'دفعة' : 'دفعات') . " &middot; {$this->fmt($instPending)}</td></tr>";
                    foreach ($pendingItems as $item) {
                        $tbody .= str_replace('<tr>', '<tr style="background:#fefce8">', $buildRow($item, $serial++));
                    }
                }

                if ($tbody === '') {
                    $tbody = '<tr><td colspan="7" style="text-align:center;color:#64748b;padding:14px">لا توجد دفعات في الفترة المحددة</td></tr>';
                }

                $installmentsHtml .= <<<BLOCK
<div style="{$pageBreakStyle}">
  <!-- Installment header card -->
  <div class="inst-header">
    <table class="inst-title-row" style="width:100%;border-collapse:collapse">
      <tr>
        <td style="text-align:right;vertical-align:middle">
          <strong class="inst-name">{$instName}</strong>
        </td>
        <td style="text-align:left;vertical-align:middle;width:80px">
          <span class="inst-badge" style="background:{$statusBg};color:{$statusColor}">{$e($statusAr)}</span>
        </td>
      </tr>
    </table>
    <div class="inst-meta">من {$e($startFmt)} إلى {$e($endFmt)} &nbsp;&middot;&nbsp; {$e($inst->months)} شهر &nbsp;&middot;&nbsp; الإجمالي: {$this->fmt($instTotal)}</div>
    <div class="progress-wrap"><div class="progress-fill" style="width:{$barWidth}%;background:{$barColor}"></div></div>
    <div class="progress-pct">{$pct}% تم سداده</div>
  </div>

  <!-- Per-installment summary -->
  <table class="card-grid inst-cards">
    <tr>
      <td><div class="k">إجمالي القسط</div><div class="v">{$this->fmt($instTotal)}</div></td>
      <td><div class="k">المدفوع</div><div class="v paid">{$this->fmt($instPaid)}</div></td>
      <td><div class="k">المتبقي</div><div class="v remaining">{$this->fmt($instRemaining)}</div></td>
    </tr>
  </table>

  <h3 class="detail-title">تفاصيل حركة السداد</h3>
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
    <tbody>{$tbody}</tbody>
  </table>
</div>
BLOCK;
            }
        }

        $body = $vendorBar
            . $summary
            . $customerSection
            . '<h2 class="section-title">تفاصيل الأقساط</h2>'
            . $installmentsHtml
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
