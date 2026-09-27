<?php

namespace App\Services;

use App\Contracts\Services\CustomerServiceInterface;
use App\Contracts\Services\InstallmentServiceInterface;
use App\Helpers\LimitsHelper;
use App\Helpers\PhoneHelper;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Protection;

class ImportService
{
    /** Bump when the template layout changes; older files are rejected. */
    public const TEMPLATE_VERSION = 1;

    /** Hidden cell that carries the template version marker. */
    private const VERSION_CELL = 'N1';

    /** Data sheet title. */
    private const DATA_SHEET = 'البيانات';

    /** Maximum number of data rows accepted from one file. */
    public const MAX_ROWS = 1000;

    /**
     * Column order (A..K). Index maps to the spreadsheet column letter.
     *
     * @var array<int, array{key: string, label: string}>
     */
    private const COLUMNS = [
        ['key' => 'name', 'label' => 'اسم العميل *'],
        ['key' => 'phone', 'label' => 'رقم الهاتف *'],
        ['key' => 'email', 'label' => 'البريد الإلكتروني'],
        ['key' => 'address', 'label' => 'العنوان'],
        ['key' => 'customer_notes', 'label' => 'ملاحظات العميل'],
        ['key' => 'installment_name', 'label' => 'اسم القسط / المنتج'],
        ['key' => 'total_amount', 'label' => 'إجمالي المبلغ'],
        ['key' => 'months', 'label' => 'عدد الشهور'],
        ['key' => 'start_date', 'label' => 'تاريخ أول قسط'],
        ['key' => 'paid_count', 'label' => 'عدد الأقساط المدفوعة'],
        ['key' => 'installment_notes', 'label' => 'ملاحظات القسط'],
    ];

    public function __construct(
        private readonly CustomerServiceInterface $customerService,
        private readonly InstallmentServiceInterface $installmentService,
    ) {}

    /**
     * Build the downloadable import template.
     */
    public function buildTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->safeTitle(self::DATA_SHEET));
        $spreadsheet->getProperties()
            ->setTitle('نموذج استيراد العملاء والأقساط')
            ->setCreator('Installment Manager');

        $lastColumn = $this->columnLetter(count(self::COLUMNS) - 1); // K

        // Header row
        foreach (self::COLUMNS as $index => $column) {
            $letter = $this->columnLetter($index);
            $sheet->setCellValue($letter.'1', $column['label']);
            $sheet->getColumnDimension($letter)->setWidth($index === 0 || $index === 5 ? 26 : 20);
        }

        $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '0D47A1']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3F2FD']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CFD8DC']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');

        // Keep phone numbers as text so leading zeros survive.
        $sheet->getStyle('B2:B'.(self::MAX_ROWS + 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        // Dates render in a readable, unambiguous format.
        $sheet->getStyle('I2:I'.(self::MAX_ROWS + 1))->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        $this->applyValidations($sheet);

        // Hidden version marker.
        $sheet->setCellValue(self::VERSION_CELL, self::TEMPLATE_VERSION);
        $sheet->getColumnDimension('N')->setVisible(false);

        // Protect the header row while leaving data cells editable.
        $sheet->getStyle('A2:'.$lastColumn.(self::MAX_ROWS + 1))
            ->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
        $sheet->getProtection()->setSheet(true);

        $this->buildInstructionsSheet($spreadsheet);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Parse an uploaded xlsx file into normalized rows.
     *
     * @return array{version_ok: bool, truncated: bool, rows: array<int, array<string, mixed>>}
     */
    public function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $sheet = $spreadsheet->getSheetByName(self::DATA_SHEET) ?? $spreadsheet->getSheet(0);

        $version = (int) $sheet->getCell(self::VERSION_CELL)->getValue();
        $versionOk = $version === self::TEMPLATE_VERSION;

        $rows = [];
        $truncated = false;

        if ($versionOk) {
            $highestRow = $sheet->getHighestDataRow();

            for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
                $raw = [];
                foreach (self::COLUMNS as $index => $column) {
                    $letter = $this->columnLetter($index);
                    $raw[$column['key']] = $sheet->getCell($letter.$rowNumber)->getValue();
                }

                if ($this->isEmptyRow($raw)) {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    $truncated = true;
                    break;
                }

                $rows[] = [
                    'line' => $rowNumber,
                    'name' => $this->str($raw['name']),
                    'phone' => $this->str($raw['phone']),
                    'email' => $this->str($raw['email']),
                    'address' => $this->str($raw['address']),
                    'customer_notes' => $this->str($raw['customer_notes']),
                    'installment_name' => $this->str($raw['installment_name']),
                    'total_amount' => $this->numeric($raw['total_amount']),
                    'months' => $this->numeric($raw['months']),
                    'start_date' => $this->normalizeDate($raw['start_date']),
                    'start_date_raw' => $this->str($raw['start_date']),
                    'paid_count' => $this->numeric($raw['paid_count']),
                    'installment_notes' => $this->str($raw['installment_notes']),
                ];
            }
        }

        // Free memory as early as possible on large files.
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'version_ok' => $versionOk,
            'truncated' => $truncated,
            'rows' => $rows,
        ];
    }

    /**
     * Validate + annotate rows and compute a preview summary.
     * Shared by both the preview endpoint and the actual import so they never diverge.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function prepare(array $rows, User $user): array
    {
        $errors = [];
        $warnings = [];
        $records = [];

        // --- Pass 1: per-row validation ---
        foreach ($rows as $row) {
            $record = $this->validateRow($row);
            if ($record['error'] !== null) {
                $errors[] = ['line' => $record['line'], 'message' => $record['error']];
            }
            $records[] = $record;
        }

        // --- Resolve existing customers for this merchant by normalized phone ---
        $phones = collect($records)
            ->filter(fn ($r) => $r['phone_normalized'] !== null)
            ->pluck('phone_normalized')
            ->unique()
            ->values()
            ->all();

        $existing = $phones === []
            ? collect()
            : Customer::query()
                ->where('user_id', $user->id)
                ->whereIn('phone_normalized', $phones)
                ->get()
                ->keyBy('phone_normalized');

        // --- Build groups (first-seen order) ---
        $groups = [];
        foreach ($records as &$record) {
            if ($record['phone_normalized'] === null || ! $record['valid']) {
                continue;
            }

            $phone = $record['phone_normalized'];
            if (! isset($groups[$phone])) {
                $existingCustomer = $existing->get($phone);
                $groups[$phone] = [
                    'phone' => $phone,
                    'existing_id' => $existingCustomer?->id,
                    'existing_name' => $existingCustomer?->name,
                    'create' => $existingCustomer === null,
                    'customer_data' => [
                        'name' => $record['customer']['name'],
                        'email' => $record['customer']['email'],
                        'phone' => $record['customer']['phone'],
                        'address' => $record['customer']['address'],
                        'notes' => $record['customer']['notes'],
                    ],
                    'first_line' => $record['line'],
                ];
            }

            // Name mismatch warnings.
            $group = $groups[$phone];
            $reference = $group['existing_id'] ? $group['existing_name'] : $group['customer_data']['name'];
            if ($reference !== null && $record['customer']['name'] !== null
                && $this->normalizeName($record['customer']['name']) !== $this->normalizeName($reference)) {
                $warnings[] = [
                    'line' => $record['line'],
                    'message' => $group['existing_id']
                        ? 'الاسم مختلف عن العميل المسجّل بنفس الرقم؛ سيتم استخدام الاسم الحالي.'
                        : 'الاسم مختلف عن أول صف لنفس الرقم؛ سيتم استخدام اسم أول صف.',
                ];
            }
        }
        unset($record);

        // --- Pass 2: quota allocation ---
        $remainingCustomers = $user->isOwner() ? PHP_INT_MAX : LimitsHelper::getRemainingCount($user->id, 'customers');
        $remainingInstallments = $user->isOwner() ? PHP_INT_MAX : LimitsHelper::getRemainingCount($user->id, 'installments');

        $newCustomers = 0;
        $matchedCustomers = 0;
        $installments = 0;
        $customerOnly = 0;

        // Decide which new-customer groups fit the plan.
        foreach ($groups as $phone => &$group) {
            if ($group['create']) {
                if ($newCustomers < $remainingCustomers) {
                    $newCustomers++;
                    $group['allowed'] = true;
                } else {
                    $group['allowed'] = false;
                }
            } else {
                $matchedCustomers++;
                $group['allowed'] = true;
            }
        }
        unset($group);

        // Finalize each record against its group + installment quota.
        foreach ($records as &$record) {
            if (! $record['valid'] || $record['phone_normalized'] === null) {
                continue;
            }

            $group = $groups[$record['phone_normalized']] ?? null;
            if ($group === null || ! $group['allowed']) {
                $record['valid'] = false;
                $record['error'] = 'تجاوز حد الباقة لعدد العملاء المسموح به.';
                $errors[] = ['line' => $record['line'], 'message' => $record['error']];

                continue;
            }

            if ($record['has_installment']) {
                if ($installments < $remainingInstallments) {
                    $installments++;
                } else {
                    $record['valid'] = false;
                    $record['error'] = 'تجاوز حد الباقة لعدد الأقساط المسموح به.';
                    $errors[] = ['line' => $record['line'], 'message' => $record['error']];
                }
            } else {
                $customerOnly++;
            }
        }
        unset($record);

        $validRows = collect($records)->where('valid', true)->count();

        return [
            'records' => $records,
            'groups' => $groups,
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'total_rows' => count($rows),
                'valid_rows' => $validRows,
                'error_rows' => count($rows) - $validRows,
                'new_customers' => $newCustomers,
                'matched_customers' => $matchedCustomers,
                'installments' => $installments,
                'customer_only' => $customerOnly,
            ],
        ];
    }

    /**
     * Execute the import. Each installment row runs in its own transaction so a
     * single bad row never rolls back the good ones (partial import).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  callable(int $processed, int $total): void|null  $onRowProcessed
     * @return array<string, mixed>
     */
    public function import(array $rows, User $user, ?callable $onRowProcessed = null): array
    {
        $prepared = $this->prepare($rows, $user);
        $records = $prepared['records'];
        $groups = $prepared['groups'];

        $total = count($records);
        $processed = 0;
        $imported = [];
        $failed = [];
        $createdCustomers = 0;

        /** @var array<string, int> $resolved phone => customer id */
        $resolved = [];

        $tick = function () use (&$processed, $total, $onRowProcessed) {
            $processed++;
            if ($onRowProcessed !== null) {
                // Throttle DB writes: report every 5 rows and on the final row.
                if ($processed % 5 === 0 || $processed === $total) {
                    $onRowProcessed($processed, $total);
                }
            }
        };

        foreach ($records as $record) {
            if (! $record['valid']) {
                $failed[] = ['line' => $record['line'], 'error' => $record['error'] ?? 'صف غير صالح'];
                $tick();

                continue;
            }

            $phone = $record['phone_normalized'];
            $group = $groups[$phone];

            try {
                // Lazily resolve/create the customer the first time we touch its group.
                if (! array_key_exists($phone, $resolved)) {
                    if ($group['existing_id']) {
                        $resolved[$phone] = (int) $group['existing_id'];
                    } else {
                        $customer = $this->customerService->createCustomer($group['customer_data'], $user);
                        $resolved[$phone] = (int) $customer->id;
                        $createdCustomers++;
                    }
                }

                $customerId = $resolved[$phone];

                if ($record['has_installment']) {
                    $this->importInstallmentRow($record, $customerId, $user);
                }

                $imported[] = $record['line'];
            } catch (\Throwable $e) {
                $failed[] = ['line' => $record['line'], 'error' => $this->safeError($e)];
            }

            $tick();
        }

        // Ensure the final progress tick fires even when the last rows were skipped.
        if ($onRowProcessed !== null && ($total === 0 || $processed !== $total)) {
            $onRowProcessed($total, $total);
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'warnings' => $prepared['warnings'],
            'created_customers' => $createdCustomers,
            'matched_customers' => $prepared['summary']['matched_customers'],
            'imported_count' => count($imported),
            'failed_count' => count($failed),
            'total_rows' => $total,
        ];
    }

    /**
     * Create one installment and mark the first N items as already paid.
     *
     * @param  array<string, mixed>  $record
     */
    private function importInstallmentRow(array $record, int $customerId, User $user): void
    {
        DB::transaction(function () use ($record, $customerId, $user) {
            $installment = $this->installmentService->createInstallment([
                'customer_id' => $customerId,
                'name' => $record['installment']['name'],
                'total_amount' => $record['installment']['total_amount'],
                'months' => $record['installment']['months'],
                'start_date' => $record['installment']['start_date'],
                'products' => [],
                'notes' => $record['installment']['notes'],
            ], $user, notify: false);

            $paidCount = (int) $record['installment']['paid_count'];
            if ($paidCount <= 0) {
                return;
            }

            $items = $installment->items()->orderBy('due_date')->orderBy('id')->get();
            foreach ($items->take($paidCount) as $item) {
                $item->update([
                    'status' => 'paid',
                    'paid_amount' => $item->amount,
                    'paid_at' => Carbon::parse($item->due_date)->startOfDay(),
                ]);
            }

            if ($paidCount >= $items->count()) {
                $installment->update(['status' => 'completed']);
            }
        });
    }

    /**
     * Validate a single parsed row and annotate it for grouping/import.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function validateRow(array $row): array
    {
        $hasInstallment = $this->rowHasInstallment($row);

        $payload = [
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'address' => $row['address'],
            'customer_notes' => $row['customer_notes'],
        ];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($hasInstallment) {
            $payload['installment_name'] = $row['installment_name'];
            $payload['total_amount'] = $row['total_amount'];
            $payload['months'] = $row['months'];
            $payload['start_date'] = $row['start_date'];
            $payload['paid_count'] = $row['paid_count'] ?? 0;
            $payload['installment_notes'] = $row['installment_notes'];

            $rules['installment_name'] = ['nullable', 'string', 'max:255'];
            $rules['total_amount'] = ['required', 'numeric', 'min:0.01'];
            $rules['months'] = ['required', 'integer', 'min:1', 'max:120'];
            $rules['start_date'] = ['required', 'date'];
            $rules['paid_count'] = ['nullable', 'integer', 'min:0', 'lte:months'];
            $rules['installment_notes'] = ['nullable', 'string', 'max:2000'];
        }

        $validator = Validator::make($payload, $rules, [], $this->attributeNames());

        // Surface the "date column had text we could not read" case clearly.
        if ($hasInstallment && $row['start_date'] === null && $row['start_date_raw'] !== null) {
            $validator->after(function ($v) {
                $v->errors()->add('start_date', 'تاريخ أول قسط غير صالح.');
            });
        }

        $error = $validator->fails()
            ? implode('، ', $validator->errors()->all())
            : null;

        return [
            'line' => $row['line'],
            'phone_normalized' => PhoneHelper::normalize($row['phone']),
            'valid' => $error === null,
            'error' => $error,
            'has_installment' => $hasInstallment,
            'customer' => [
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'address' => $row['address'],
                'notes' => $row['customer_notes'],
            ],
            'installment' => $hasInstallment ? [
                'name' => $row['installment_name'],
                'total_amount' => $row['total_amount'] !== null ? (float) $row['total_amount'] : null,
                'months' => $row['months'] !== null ? (int) $row['months'] : null,
                'start_date' => $row['start_date'],
                'paid_count' => $row['paid_count'] !== null ? (int) $row['paid_count'] : 0,
                'notes' => $row['installment_notes'],
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowHasInstallment(array $row): bool
    {
        foreach (['installment_name', 'total_amount', 'months', 'start_date', 'installment_notes'] as $key) {
            if ($row[$key] !== null && $row[$key] !== '') {
                return true;
            }
        }

        return $row['paid_count'] !== null && (int) $row['paid_count'] > 0;
    }

    private function applyValidations($sheet): void
    {
        $rangeEnd = self::MAX_ROWS + 1;

        $decimal = function (string $column, string $formula1, string $prompt) use ($sheet, $rangeEnd) {
            for ($row = 2; $row <= $rangeEnd; $row++) {
                $validation = $sheet->getCell($column.$row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_DECIMAL);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('قيمة غير صالحة');
                $validation->setError($prompt);
                $validation->setOperator(DataValidation::OPERATOR_GREATERTHAN);
                $validation->setFormula1($formula1);
            }
        };

        // G: total amount > 0
        $decimal('G', '0', 'أدخل مبلغاً أكبر من صفر.');

        // H: months whole 1..120
        for ($row = 2; $row <= $rangeEnd; $row++) {
            $v = $sheet->getCell('H'.$row)->getDataValidation();
            $v->setType(DataValidation::TYPE_WHOLE);
            $v->setErrorStyle(DataValidation::STYLE_STOP);
            $v->setAllowBlank(true);
            $v->setShowErrorMessage(true);
            $v->setErrorTitle('عدد شهور غير صالح');
            $v->setError('عدد الشهور يجب أن يكون رقماً صحيحاً بين 1 و 120.');
            $v->setOperator(DataValidation::OPERATOR_BETWEEN);
            $v->setFormula1('1');
            $v->setFormula2('120');
        }

        // J: paid count whole >= 0
        for ($row = 2; $row <= $rangeEnd; $row++) {
            $v = $sheet->getCell('J'.$row)->getDataValidation();
            $v->setType(DataValidation::TYPE_WHOLE);
            $v->setErrorStyle(DataValidation::STYLE_STOP);
            $v->setAllowBlank(true);
            $v->setShowErrorMessage(true);
            $v->setErrorTitle('قيمة غير صالحة');
            $v->setError('عدد الأقساط المدفوعة يجب أن يكون صفراً أو أكثر.');
            $v->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
            $v->setFormula1('0');
        }
    }

    private function buildInstructionsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->safeTitle('تعليمات'));
        $sheet->setRightToLeft(true);

        $sheet->setCellValue('A1', 'تعليمات تعبئة ملف الاستيراد');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $lines = [
            ['العمود', 'الوصف'],
            ['اسم العميل *', 'إجباري. اسم العميل الكامل.'],
            ['رقم الهاتف *', 'إجباري. يُستخدم لتجميع الأقساط تحت نفس العميل. اتركه كنص للحفاظ على الصفر في البداية.'],
            ['البريد الإلكتروني', 'اختياري.'],
            ['العنوان', 'اختياري.'],
            ['ملاحظات العميل', 'اختياري.'],
            ['اسم القسط / المنتج', 'اختياري. اتركه فارغاً لتسجيل العميل فقط بدون قسط.'],
            ['إجمالي المبلغ', 'إجباري عند وجود قسط. رقم أكبر من صفر.'],
            ['عدد الشهور', 'إجباري عند وجود قسط. رقم صحيح بين 1 و 120.'],
            ['تاريخ أول قسط', 'إجباري عند وجود قسط. مثال: 2026-01-15.'],
            ['عدد الأقساط المدفوعة', 'اختياري. القيمة الافتراضية 0. يعلّم أول N قسط كمدفوع.'],
            ['ملاحظات القسط', 'اختياري.'],
            [],
            ['أمثلة:'],
            ['اسم العميل', 'رقم الهاتف', 'اسم القسط', 'إجمالي المبلغ', 'عدد الشهور', 'تاريخ أول قسط', 'عدد الأقساط المدفوعة'],
            ['أحمد علي', '01000000001', 'تلفزيون', '12000', '12', '2026-01-01', '2'],
            ['سارة محمد', '01000000002', 'ثلاجة', '8000', '8', '2026-02-15', '0'],
        ];

        $row = 3;
        foreach ($lines as $cells) {
            $col = 0;
            foreach ($cells as $value) {
                $sheet->setCellValueExplicit(
                    $this->columnLetter($col).$row,
                    $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
                $col++;
            }
            $row++;
        }

        foreach (range('A', 'G') as $letter) {
            $sheet->getColumnDimension($letter)->setWidth(24);
        }
    }

    // ---- small helpers -------------------------------------------------

    private function columnLetter(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
    }

    private function safeTitle(string $title): string
    {
        return mb_substr($title, 0, 31);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function isEmptyRow(array $raw): bool
    {
        foreach ($raw as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function numeric(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $text = trim((string) $value);
        if ($text === '' || ! is_numeric($text)) {
            return $text === '' ? null : $text; // keep invalid text so the validator reports it
        }

        return $text + 0;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeName(?string $name): string
    {
        return mb_strtolower(trim((string) $name));
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return [
            'name' => 'اسم العميل',
            'email' => 'البريد الإلكتروني',
            'phone' => 'رقم الهاتف',
            'address' => 'العنوان',
            'customer_notes' => 'ملاحظات العميل',
            'installment_name' => 'اسم القسط',
            'total_amount' => 'إجمالي المبلغ',
            'months' => 'عدد الشهور',
            'start_date' => 'تاريخ أول قسط',
            'paid_count' => 'عدد الأقساط المدفوعة',
            'installment_notes' => 'ملاحظات القسط',
        ];
    }

    private function safeError(\Throwable $e): string
    {
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            $message = $e->getMessage();
            if ($message !== '') {
                return $message;
            }
        }

        return 'تعذر حفظ هذا الصف.';
    }
}
