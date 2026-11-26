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
            'installment_id'    => ['sometimes', 'nullable', 'integer', 'min:1'],
            'installment_ids'   => ['sometimes', 'nullable', 'array'],
            'installment_ids.*' => ['integer', 'min:1'],
            'date_from'         => ['sometimes', 'nullable', 'date'],
            'date_to'           => ['sometimes', 'nullable', 'date'],
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

        // Support both installment_ids (array) and legacy installment_id (single)
        $ids = [];
        if (! empty($filters['installment_ids']) && is_array($filters['installment_ids'])) {
            $ids = array_map('intval', $filters['installment_ids']);
        } elseif (! empty($filters['installment_id'])) {
            $ids = [(int) $filters['installment_id']];
        }
        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        }

        return $query->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Installment>  $installments
     * @param  array<string, mixed>  $filters
     */
    private function buildStatementHtml(Customer $customer, \Illuminate\Database\Eloquent\Collection $installments, array $filters): string
    {
        $e     = fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $now   = now()->format('Y-m-d H:i');
        $today = now()->startOfDay();

        /** @var \App\Models\User|null $vendor */
        $vendor         = $customer->user ?? null;
        $vendorName     = $vendor?->name ?? '';
        $vendorPhone    = $vendor?->phone ?? '';
        $vendorLogoPath = $vendor?->logo ?? null;

        // ── Vendor logo ──────────────────────────────────────────────────────
        $logoHtml = '';
        if ($vendorLogoPath) {
            $logoAbsPath = storage_path('app/public/' . ltrim($vendorLogoPath, '/'));
            if (file_exists($logoAbsPath)) {
                $ext      = strtolower(pathinfo($logoAbsPath, PATHINFO_EXTENSION));
                $mime     = match ($ext) { 'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg' };
                $b64      = base64_encode((string) file_get_contents($logoAbsPath));
                $logoHtml = "<img src=\"data:{$mime};base64,{$b64}\" style=\"height:56px;max-width:140px;object-fit:contain;vertical-align:middle\" />";
            }
        }

        // ── Page header (vendor card + customer subtitle) ────────────────────
        $vendorNameHtml  = $vendorName  ? '<div style="font-size:14pt;font-weight:bold;color:#0f172a">' . $e($vendorName) . '</div>' : '';
        $vendorPhoneHtml = $vendorPhone ? '<div style="font-size:10.5pt;color:#475569;margin-top:3px">&#128222; ' . $e($vendorPhone) . '</div>' : '';
        $logoCell        = $logoHtml    ? "<td style=\"text-align:left;width:150px;vertical-align:middle\">{$logoHtml}</td>" : '';

        $customerLine = $e($customer->name);
        if ($customer->phone) {
            $customerLine .= ' &nbsp;&middot;&nbsp; ' . $e($customer->phone);
        }

        $filterNote = '';
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $from       = $filters['date_from'] ?? '';
            $to         = $filters['date_to']   ?? '';
            $filterNote = " &nbsp;&middot;&nbsp; " . $e($from) . " \u{2013} " . $e($to);
        }

        $pageHeader = <<<HTML
<table style="width:100%;border-collapse:collapse;border:1.5px solid #e2e8f0;border-radius:10px;margin-bottom:16px;background:#f8fafc">
  <tr>
    <td style="padding:14px 16px;text-align:right;vertical-align:middle">
      {$vendorNameHtml}
      {$vendorPhoneHtml}
    </td>
    {$logoCell}
  </tr>
</table>
<div style="background:#f0f7ff;border:2px solid #1e3a8a;border-radius:8px;padding:12px 16px;margin-bottom:18px">
  <div style="font-size:11pt;font-weight:bold;color:#1e3a8a">&#1603;&#1588;&#1601; &#1581;&#1587;&#1575;&#1576;</div>
  <div style="font-size:10pt;color:#1e40af;margin-top:4px">&#1604;&#1604;&#1593;&#1605;&#1610;&#1604;: {$customerLine}{$filterNote}</div>
  <div style="font-size:9pt;color:#475569;margin-top:2px">&#1578;&#1575;&#1585;&#1610;&#1582; &#1575;&#1604;&#1573;&#1589;&#1583;&#1575;&#1585;: {$now}</div>
</div>
HTML;

        // ── Installments ─────────────────────────────────────────────────────
        $installmentsHtml = '';

        if ($installments->isEmpty()) {
            $installmentsHtml = '<p style="color:#64748b;text-align:center;padding:20px">&#1604;&#1575; &#1578;&#1608;&#1580;&#1583; &#1576;&#1610;&#1575;&#1606;&#1575;&#1578; &#1576;&#1575;&#1604;&#1601;&#1578;&#1585;&#1577; &#1575;&#1604;&#1605;&#1581;&#1583;&#1583;&#1577;</p>';
        } else {
            foreach ($installments as $inst) {
                $items = $inst->items->sortBy('due_date');

                $tbody  = '';
                $serial = 1;

                foreach ($items as $item) {
                    $due    = \Carbon\Carbon::parse($item->due_date);
                    $isOver = $item->status !== 'paid' && $due->startOfDay()->lt($today);

                    [$statusText, $rowBg] = match (true) {
                        $item->status === 'paid'               => ['&#1605;&#1583;&#1601;&#1608;&#1593;',  '#f0fdf4'],
                        $item->status === 'partial'            => ['&#1580;&#1586;&#1574;&#1610;',         '#fff7ed'],
                        $item->status === 'overdue' || $isOver => ['&#1605;&#1578;&#1571;&#1582;&#1585;', '#fef2f2'],
                        default                                => ['&#1605;&#1593;&#1604;&#1602;',         '#fefce8'],
                    };

                    $paidAt    = $item->paid_at ? \Carbon\Carbon::parse($item->paid_at)->format('Y-m-d') : '&mdash;';
                    $dueFmt    = $e($due->format('Y-m-d'));
                    $amtFmt    = $this->fmt((float) $item->amount);
                    $paidFmt   = $this->fmt((float) ($item->paid_amount ?? 0));

                    $tbody .= <<<TR
<tr style="background:{$rowBg}">
  <td style="text-align:center;width:26px;color:#64748b">{$serial}</td>
  <td>{$dueFmt}</td>
  <td class="ltr" style="color:#166534">{$amtFmt}</td>
  <td class="ltr" style="color:#1d4ed8">{$paidFmt}</td>
  <td style="color:#475569">{$paidAt}</td>
  <td style="text-align:center;font-weight:bold">{$statusText}</td>
</tr>
TR;
                    $serial++;
                }

                if ($tbody === '') {
                    $tbody = '<tr><td colspan="6" style="text-align:center;color:#64748b;padding:14px">&#1604;&#1575; &#1578;&#1608;&#1580;&#1583; &#1583;&#1601;&#1593;&#1575;&#1578; &#1601;&#1610; &#1575;&#1604;&#1601;&#1578;&#1585;&#1577; &#1575;&#1604;&#1605;&#1581;&#1583;&#1583;&#1577;</td></tr>';
                }

                $instName = $e($inst->name ?: ('&#1602;&#1587;&#1591; #' . $inst->id));
                $metaParts = [];
                $metaParts[] = '&#1587;&#1593;&#1585; &#1575;&#1604;&#1602;&#1587;&#1591;: '.$this->fmt((float) $inst->total_amount);
                if ($inst->invoice_price !== null) {
                    $metaParts[] = '&#1587;&#1593;&#1585; &#1575;&#1604;&#1601;&#1575;&#1578;&#1608;&#1585;&#1577;: '.$this->fmt((float) $inst->invoice_price);
                }
                if ($inst->down_payment !== null) {
                    $metaParts[] = '&#1575;&#1604;&#1605;&#1602;&#1583;&#1605;: '.$this->fmt((float) $inst->down_payment);
                }
                $metaHtml = implode(' &nbsp;&middot;&nbsp; ', $metaParts);
                $instHeader = <<<HEAD
<div style="margin-top:16px;margin-bottom:6px;border-right:4px solid #2563eb;padding:7px 12px;background:#eff6ff;border-radius:0 8px 8px 0">
  <strong style="font-size:11.5pt;color:#1e3a8a">{$instName}</strong>
  <div style="font-size:9.5pt;color:#334155;margin-top:4px">{$metaHtml}</div>
</div>
HEAD;

                $installmentsHtml .= $instHeader . <<<TABLE
<table class="data">
  <thead>
    <tr>
      <th>#</th>
      <th>&#1578;&#1575;&#1585;&#1610;&#1582; &#1575;&#1604;&#1575;&#1587;&#1578;&#1581;&#1602;&#1575;&#1602;</th>
      <th>&#1575;&#1604;&#1605;&#1576;&#1604;&#1594;</th>
      <th>&#1575;&#1604;&#1605;&#1583;&#1601;&#1608;&#1593;</th>
      <th>&#1578;&#1575;&#1585;&#1610;&#1582; &#1575;&#1604;&#1587;&#1583;&#1575;&#1583;</th>
      <th>&#1575;&#1604;&#1581;&#1575;&#1604;&#1577;</th>
    </tr>
  </thead>
  <tbody>{$tbody}</tbody>
</table>
TABLE;
            }
        }

        $styles = RtlPdfDocument::baseStyles();

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>&#1603;&#1588;&#1601; &#1581;&#1587;&#1575;&#1576;</title>
<style>
{$styles}
</style>
</head>
<body>
{$pageHeader}
{$installmentsHtml}
<p class="footer-note">&#1603;&#1588;&#1601; &#1581;&#1587;&#1575;&#1576; &#1583;&#1575;&#1582;&#1604;&#1610; &#1605;&#1606; &#1606;&#1592;&#1575;&#1605; &#171;&#1575;&#1602;&#1587;&#1575;&#1591;&#1610;&#187; &middot; &#1604;&#1604;&#1575;&#1587;&#1578;&#1582;&#1583;&#1575;&#1605; &#1575;&#1604;&#1578;&#1588;&#1594;&#1610;&#1604;&#1610; &#1601;&#1602;&#1591;.</p>
</body>
</html>
HTML;
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

            $instSheet->setCellValue('A1', 'سعر القسط');
            $instSheet->setCellValue('B1', (float) $inst->total_amount);
            $instSheet->setCellValue('A2', 'سعر الفاتورة');
            $instSheet->setCellValue('B2', $inst->invoice_price !== null ? (float) $inst->invoice_price : '');
            $instSheet->setCellValue('A3', 'المقدم');
            $instSheet->setCellValue('B3', $inst->down_payment !== null ? (float) $inst->down_payment : '');

            $headers = ['#', 'تاريخ الاستحقاق', 'المبلغ', 'المدفوع', 'تاريخ السداد', 'المرجع', 'الحالة'];
            $col     = 1;
            foreach ($headers as $h) {
                $instSheet->setCellValue(Coordinate::stringFromColumnIndex($col).'5', $h);
                $col++;
            }

            $rowNum = 6;
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
