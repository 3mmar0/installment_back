<?php

namespace App\Services;

use App\Contracts\Services\CustomerServiceInterface;
use App\Contracts\Services\InstallmentServiceInterface;
use App\Helpers\LimitsHelper;
use App\Helpers\NationalIdHelper;
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
    public const TEMPLATE_VERSION = 4;

    /** Import types. */
    public const TYPE_CUSTOMERS = 'customers';

    public const TYPE_INSTALLMENTS = 'installments';

    /** Hidden cell that carries the template version marker. */
    private const VERSION_CELL = 'Z1';

    /** Hidden cell that carries the template type marker. */
    private const TYPE_CELL = 'Y1';

    /** Data sheet title for the customers template. */
    private const DATA_SHEET_CUSTOMERS = 'البيانات';

    /** Data sheet title for the installments template. */
    private const DATA_SHEET_INSTALLMENTS = 'الأقساط';

    /** Maximum number of data rows accepted from one file. */
    public const MAX_ROWS = 1000;

    /**
     * Customers template columns. Index maps to the spreadsheet column letter.
     *
     * @var array<int, array{key: string, label: string}>
     */
    private const COLUMNS_CUSTOMERS = [
        ['key' => 'name', 'label' => 'اسم العميل *'],
        ['key' => 'national_id', 'label' => 'الرقم القومي'],
        ['key' => 'phone', 'label' => 'رقم الهاتف'],
        ['key' => 'email', 'label' => 'البريد الإلكتروني'],
        ['key' => 'address', 'label' => 'العنوان'],
        ['key' => 'job', 'label' => 'الوظيفة'],
        ['key' => 'monthly_salary', 'label' => 'المرتب الشهري (ج.م)'],
        ['key' => 'customer_notes', 'label' => 'ملاحظات العميل'],
        ['key' => 'guarantor_name', 'label' => 'اسم الضامن'],
        ['key' => 'guarantor_national_id', 'label' => 'الرقم القومي للضامن'],
        ['key' => 'guarantor_phone', 'label' => 'هاتف الضامن'],
        ['key' => 'installment_name', 'label' => 'اسم القسط / المنتج'],
        ['key' => 'total_amount', 'label' => 'إجمالي المبلغ'],
        ['key' => 'months', 'label' => 'عدد الشهور'],
        ['key' => 'start_date', 'label' => 'تاريخ أول قسط'],
        ['key' => 'paid_count', 'label' => 'عدد الأقساط المدفوعة'],
        ['key' => 'installment_notes', 'label' => 'ملاحظات القسط'],
    ];

    /**
     * Installments template columns (A..F). All rows attach to one chosen customer.
     *
     * @var array<int, array{key: string, label: string}>
     */
    private const COLUMNS_INSTALLMENTS = [
        ['key' => 'installment_name', 'label' => 'اسم القسط / المنتج'],
        ['key' => 'total_amount', 'label' => 'إجمالي المبلغ *'],
        ['key' => 'months', 'label' => 'عدد الشهور *'],
        ['key' => 'start_date', 'label' => 'تاريخ أول قسط *'],
        ['key' => 'paid_count', 'label' => 'عدد الأقساط المدفوعة'],
        ['key' => 'installment_notes', 'label' => 'ملاحظات القسط'],
    ];

    /**
     * The single example row shown inside each template so the layout is clear.
     * Parse and save both ignore this leftover row, even if Excel changes types.
     *
     * @var array<string, mixed>
     */
    private const EXAMPLE_CUSTOMERS = [
        'name' => 'أحمد علي',
        'national_id' => '29001011234567',
        'phone' => '01000000000',
        'email' => 'ahmed@example.com',
        'address' => 'القاهرة - مصر الجديدة',
        'job' => 'محاسب',
        'monthly_salary' => 12000,
        'customer_notes' => 'صف مثال — احذفه أو استبدله ببياناتك',
        'guarantor_name' => 'محمد ضامن',
        'guarantor_national_id' => '28501011234567',
        'guarantor_phone' => '01011111111',
        'installment_name' => 'تلفزيون سامسونج',
        'total_amount' => 12000,
        'months' => 12,
        'start_date' => '2026-01-01',
        'paid_count' => 2,
        'installment_notes' => 'ملاحظة توضيحية',
    ];

    /** @var array<string, mixed> */
    private const EXAMPLE_INSTALLMENTS = [
        'installment_name' => 'تلفزيون سامسونج',
        'total_amount' => 12000,
        'months' => 12,
        'start_date' => '2026-01-01',
        'paid_count' => 2,
        'installment_notes' => 'ملاحظة توضيحية',
    ];

    public function __construct(
        private readonly CustomerServiceInterface $customerService,
        private readonly InstallmentServiceInterface $installmentService,
    ) {}

    /**
     * Build the downloadable import template for the given type.
     */
    public function buildTemplate(string $type = self::TYPE_CUSTOMERS): Spreadsheet
    {
        $type = $this->normalizeType($type);
        $columns = $this->columnsFor($type);

        $spreadsheet = new Spreadsheet;

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->safeTitle($this->dataSheetFor($type)));
        $spreadsheet->getProperties()
            ->setTitle($type === self::TYPE_INSTALLMENTS ? 'نموذج استيراد الأقساط' : 'نموذج استيراد العملاء والأقساط')
            ->setCreator('Installment Manager');

        $lastColumn = $this->columnLetter(count($columns) - 1);

        // Header row
        foreach ($columns as $index => $column) {
            $letter = $this->columnLetter($index);
            $sheet->setCellValue($letter.'1', $column['label']);
            $sheet->getColumnDimension($letter)->setWidth(in_array($column['key'], ['name', 'installment_name'], true) ? 26 : 20);
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

        // Keep identity numbers as text so leading zeros survive.
        foreach (['phone', 'national_id', 'guarantor_national_id', 'guarantor_phone'] as $textKey) {
            $letter = $this->letterForKey($columns, $textKey);
            if ($letter !== null) {
                $sheet->getStyle($letter.'2:'.$letter.(self::MAX_ROWS + 1))
                    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
        }

        // Dates render in a readable, unambiguous format.
        $dateLetter = $this->letterForKey($columns, 'start_date');
        if ($dateLetter !== null) {
            $sheet->getStyle($dateLetter.'2:'.$dateLetter.(self::MAX_ROWS + 1))
                ->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        }

        $this->applyValidations($sheet, $columns);

        // One example row (row 2) so the expected layout is obvious.
        $this->writeExampleRow($sheet, $columns, $type);

        // Hidden version + type markers.
        $sheet->setCellValueExplicit(self::VERSION_CELL, (string) self::TEMPLATE_VERSION, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit(self::TYPE_CELL, $type, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->getColumnDimension('Y')->setVisible(false);
        $sheet->getColumnDimension('Z')->setVisible(false);

        // Protect the header row while leaving data cells editable.
        $sheet->getStyle('A2:'.$lastColumn.(self::MAX_ROWS + 1))
            ->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
        $sheet->getProtection()->setSheet(true);

        $this->buildInstructionsSheet($spreadsheet, $type);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Parse an uploaded xlsx file into normalized rows.
     *
     * @return array{version_ok: bool, truncated: bool, rows: array<int, array<string, mixed>>}
     */
    public function parse(string $path, string $type = self::TYPE_CUSTOMERS): array
    {
        $type = $this->normalizeType($type);
        $columns = $this->columnsFor($type);

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $sheet = $spreadsheet->getSheetByName($this->dataSheetFor($type)) ?? $spreadsheet->getSheet(0);

        $version = (int) $sheet->getCell(self::VERSION_CELL)->getValue();
        // Missing type marker (older customers templates) defaults to customers.
        $fileType = $this->str($sheet->getCell(self::TYPE_CELL)->getValue()) ?? self::TYPE_CUSTOMERS;

        $versionOk = $version === self::TEMPLATE_VERSION && $fileType === $type;

        $rows = [];
        $truncated = false;

        if ($versionOk) {
            $highestRow = $sheet->getHighestDataRow();

            for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
                $raw = [];
                foreach ($columns as $index => $column) {
                    $letter = $this->columnLetter($index);
                    $raw[$column['key']] = $sheet->getCell($letter.$rowNumber)->getValue();
                }

                if ($this->isEmptyRow($raw)) {
                    continue;
                }

                $parsedRow = $this->normalizeRow($raw, $rowNumber, $type);

                // Skip the built-in example row if the user left it untouched.
                if ($this->isExampleRow($parsedRow, $type)) {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    $truncated = true;
                    break;
                }

                $rows[] = $parsedRow;
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
    public function prepare(array $rows, User $user, string $type = self::TYPE_CUSTOMERS, ?int $customerId = null): array
    {
        return $this->normalizeType($type) === self::TYPE_INSTALLMENTS
            ? $this->prepareInstallments($rows, $user, $customerId)
            : $this->prepareCustomers($rows, $user);
    }

    /**
     * Execute the import. Each installment row runs in its own transaction so a
     * single bad row never rolls back the good ones (partial import).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  callable(int $processed, int $total): void|null  $onRowProcessed
     * @return array<string, mixed>
     */
    public function import(array $rows, User $user, ?callable $onRowProcessed = null, string $type = self::TYPE_CUSTOMERS, ?int $customerId = null): array
    {
        return $this->normalizeType($type) === self::TYPE_INSTALLMENTS
            ? $this->importInstallments($rows, $user, $customerId, $onRowProcessed)
            : $this->importCustomers($rows, $user, $onRowProcessed);
    }

    // ---- customers flow ------------------------------------------------

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function prepareCustomers(array $rows, User $user): array
    {
        $rows = $this->withoutExampleRows($rows, self::TYPE_CUSTOMERS);
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

        // --- Resolve existing customers by national id first, then phone ---
        $nationalIds = collect($records)
            ->pluck('national_id_normalized')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $phones = collect($records)
            ->pluck('phone_normalized')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingByNid = $nationalIds === []
            ? collect()
            : Customer::query()
                ->where('user_id', $user->id)
                ->whereIn('national_id', $nationalIds)
                ->get()
                ->keyBy('national_id');

        $existingByPhone = $phones === []
            ? collect()
            : Customer::query()
                ->where('user_id', $user->id)
                ->whereIn('phone_normalized', $phones)
                ->get()
                ->keyBy('phone_normalized');

        // --- Build groups (first-seen order) ---
        $groups = [];
        foreach ($records as &$record) {
            if ($record['identity_key'] === null || ! $record['valid']) {
                continue;
            }

            $key = $record['identity_key'];
            if (! isset($groups[$key])) {
                $existingCustomer = $record['national_id_normalized'] !== null
                    ? $existingByNid->get($record['national_id_normalized'])
                    : $existingByPhone->get($record['phone_normalized']);
                $groups[$key] = [
                    'identity_key' => $key,
                    'existing_id' => $existingCustomer?->id,
                    'existing_name' => $existingCustomer?->name,
                    'create' => $existingCustomer === null,
                    'customer_data' => [
                        'name' => $record['customer']['name'],
                        'email' => $record['customer']['email'],
                        'phone' => $record['customer']['phone'],
                        'national_id' => $record['customer']['national_id'],
                        'address' => $record['customer']['address'],
                        'job' => $record['customer']['job'],
                        'monthly_salary' => $record['customer']['monthly_salary'],
                        'notes' => $record['customer']['notes'],
                        'guarantor_name' => $record['customer']['guarantor_name'],
                        'guarantor_national_id' => $record['customer']['guarantor_national_id'],
                        'guarantor_phone' => $record['customer']['guarantor_phone'],
                    ],
                    'first_line' => $record['line'],
                ];
            }

            $group = $groups[$key];
            $reference = $group['existing_id'] ? $group['existing_name'] : $group['customer_data']['name'];
            if ($reference !== null && $record['customer']['name'] !== null
                && $this->normalizeName($record['customer']['name']) !== $this->normalizeName($reference)) {
                $warnings[] = [
                    'line' => $record['line'],
                    'message' => $group['existing_id']
                        ? 'الاسم مختلف عن العميل المسجّل بنفس المعرّف؛ سيتم استخدام الاسم الحالي.'
                        : 'الاسم مختلف عن أول صف لنفس المعرّف؛ سيتم استخدام اسم أول صف.',
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
            if (! $record['valid'] || $record['identity_key'] === null) {
                continue;
            }

            $group = $groups[$record['identity_key']] ?? null;
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
            'type' => self::TYPE_CUSTOMERS,
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
                'customer_name' => null,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function importCustomers(array $rows, User $user, ?callable $onRowProcessed = null): array
    {
        $prepared = $this->prepareCustomers($rows, $user);
        $records = $prepared['records'];
        $groups = $prepared['groups'];

        $total = count($records);
        $processed = 0;
        $imported = [];
        $failed = [];
        $createdCustomers = 0;

        /** @var array<string, int> $resolved identity_key => customer id */
        $resolved = [];

        $tick = $this->tickFactory($processed, $total, $onRowProcessed);

        foreach ($records as $record) {
            if (! $record['valid']) {
                $failed[] = ['line' => $record['line'], 'error' => $record['error'] ?? 'صف غير صالح'];
                $tick();

                continue;
            }

            $key = $record['identity_key'];
            $group = $groups[$key];

            try {
                // Lazily resolve/create the customer the first time we touch its group.
                if (! array_key_exists($key, $resolved)) {
                    if ($group['existing_id']) {
                        $resolved[$key] = (int) $group['existing_id'];
                    } else {
                        $customer = $this->customerService->createCustomer($group['customer_data'], $user);
                        $resolved[$key] = (int) $customer->id;
                        $createdCustomers++;
                    }
                }

                $customerId = $resolved[$key];

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

    // ---- installments flow (attach to one chosen customer) -------------

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function prepareInstallments(array $rows, User $user, ?int $customerId): array
    {
        $rows = $this->withoutExampleRows($rows, self::TYPE_INSTALLMENTS);
        $customer = $customerId === null
            ? null
            : Customer::query()->where('user_id', $user->id)->find($customerId);

        $errors = [];
        $records = [];

        foreach ($rows as $row) {
            $record = $this->validateInstallmentRow($row);

            if ($customer === null && $record['valid']) {
                $record['valid'] = false;
                $record['error'] = 'العميل غير موجود.';
            }

            if ($record['error'] !== null) {
                $errors[] = ['line' => $record['line'], 'message' => $record['error']];
            }
            $records[] = $record;
        }

        // Installment quota allocation.
        $remainingInstallments = $user->isOwner() ? PHP_INT_MAX : LimitsHelper::getRemainingCount($user->id, 'installments');
        $installments = 0;

        foreach ($records as &$record) {
            if (! $record['valid']) {
                continue;
            }

            if ($installments < $remainingInstallments) {
                $installments++;
            } else {
                $record['valid'] = false;
                $record['error'] = 'تجاوز حد الباقة لعدد الأقساط المسموح به.';
                $errors[] = ['line' => $record['line'], 'message' => $record['error']];
            }
        }
        unset($record);

        $validRows = collect($records)->where('valid', true)->count();

        return [
            'type' => self::TYPE_INSTALLMENTS,
            'records' => $records,
            'customer_id' => $customer?->id,
            'errors' => $errors,
            'warnings' => [],
            'summary' => [
                'total_rows' => count($rows),
                'valid_rows' => $validRows,
                'error_rows' => count($rows) - $validRows,
                'new_customers' => 0,
                'matched_customers' => $customer !== null ? 1 : 0,
                'installments' => $installments,
                'customer_only' => 0,
                'customer_name' => $customer?->name,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function importInstallments(array $rows, User $user, ?int $customerId, ?callable $onRowProcessed = null): array
    {
        $prepared = $this->prepareInstallments($rows, $user, $customerId);
        $records = $prepared['records'];
        $resolvedCustomerId = $prepared['customer_id'];

        $total = count($records);
        $processed = 0;
        $imported = [];
        $failed = [];

        $tick = $this->tickFactory($processed, $total, $onRowProcessed);

        foreach ($records as $record) {
            if (! $record['valid'] || $resolvedCustomerId === null) {
                $failed[] = ['line' => $record['line'], 'error' => $record['error'] ?? 'صف غير صالح'];
                $tick();

                continue;
            }

            try {
                $this->importInstallmentRow($record, (int) $resolvedCustomerId, $user);
                $imported[] = $record['line'];
            } catch (\Throwable $e) {
                $failed[] = ['line' => $record['line'], 'error' => $this->safeError($e)];
            }

            $tick();
        }

        if ($onRowProcessed !== null && ($total === 0 || $processed !== $total)) {
            $onRowProcessed($total, $total);
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'warnings' => [],
            'created_customers' => 0,
            'matched_customers' => $prepared['summary']['matched_customers'],
            'imported_count' => count($imported),
            'failed_count' => count($failed),
            'total_rows' => $total,
        ];
    }

    /**
     * Build the throttled progress callback shared by both import flows.
     *
     * @param  callable(int, int): void|null  $onRowProcessed
     */
    private function tickFactory(int &$processed, int $total, ?callable $onRowProcessed): callable
    {
        return function () use (&$processed, $total, $onRowProcessed) {
            $processed++;
            if ($onRowProcessed !== null) {
                // Throttle DB writes: report every 5 rows and on the final row.
                if ($processed % 5 === 0 || $processed === $total) {
                    $onRowProcessed($processed, $total);
                }
            }
        };
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

        app(\App\Services\CreditScore\CreditScoreRecalculationDispatcher::class)
            ->dispatchForCustomerId($customerId);
    }

    /**
     * Validate a single parsed customers-row and annotate it for grouping/import.
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
            'national_id' => $row['national_id'] ?? null,
            'address' => $row['address'],
            'job' => $row['job'] ?? null,
            'monthly_salary' => $row['monthly_salary'],
            'customer_notes' => $row['customer_notes'],
            'guarantor_name' => $row['guarantor_name'] ?? null,
            'guarantor_national_id' => $row['guarantor_national_id'] ?? null,
            'guarantor_phone' => $row['guarantor_phone'] ?? null,
        ];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required_without:national_id', 'nullable', 'string', 'max:50'],
            'national_id' => ['required_without:phone', 'nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'job' => ['nullable', 'string', 'max:255'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'guarantor_name' => ['nullable', 'string', 'max:255'],
            'guarantor_national_id' => ['nullable', 'string', 'max:20'],
            'guarantor_phone' => ['nullable', 'string', 'max:50'],
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

        $validator = Validator::make($payload, $rules, [
            'phone.required_without' => 'أدخل الرقم القومي أو رقم الهاتف.',
            'national_id.required_without' => 'أدخل الرقم القومي أو رقم الهاتف.',
        ], $this->attributeNames());

        $validator->after(function ($v) use ($payload) {
            $nid = $payload['national_id'] ?? null;
            if ($nid !== null && $nid !== '' && ! NationalIdHelper::isValid((string) $nid)) {
                $v->errors()->add('national_id', 'الرقم القومي يجب أن يكون 14 رقماً.');
            }
            $guarantorNid = $payload['guarantor_national_id'] ?? null;
            if ($guarantorNid !== null && $guarantorNid !== '' && ! NationalIdHelper::isValid((string) $guarantorNid)) {
                $v->errors()->add('guarantor_national_id', 'الرقم القومي للضامن يجب أن يكون 14 رقماً.');
            }
        });

        // Surface the "date column had text we could not read" case clearly.
        if ($hasInstallment && $row['start_date'] === null && $row['start_date_raw'] !== null) {
            $validator->after(function ($v) {
                $v->errors()->add('start_date', 'تاريخ أول قسط غير صالح.');
            });
        }

        $nationalId = NationalIdHelper::normalize($row['national_id'] ?? null);
        $phoneNormalized = PhoneHelper::normalize($row['phone'] ?? null);
        $identityKey = $nationalId !== null
            ? 'nid:'.$nationalId
            : ($phoneNormalized !== null ? 'phone:'.$phoneNormalized : null);

        $error = $validator->fails()
            ? implode('، ', $validator->errors()->all())
            : null;

        return [
            'line' => $row['line'],
            'identity_key' => $identityKey,
            'national_id_normalized' => $nationalId,
            'phone_normalized' => $phoneNormalized,
            'valid' => $error === null,
            'error' => $error,
            'has_installment' => $hasInstallment,
            'customer' => [
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'national_id' => $row['national_id'] ?? null,
                'address' => $row['address'],
                'job' => $row['job'] ?? null,
                'monthly_salary' => $row['monthly_salary'] !== null ? (float) $row['monthly_salary'] : null,
                'notes' => $row['customer_notes'],
                'guarantor_name' => $row['guarantor_name'] ?? null,
                'guarantor_national_id' => $row['guarantor_national_id'] ?? null,
                'guarantor_phone' => $row['guarantor_phone'] ?? null,
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
     * Validate a single parsed installments-row (installments template).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function validateInstallmentRow(array $row): array
    {
        $payload = [
            'installment_name' => $row['installment_name'],
            'total_amount' => $row['total_amount'],
            'months' => $row['months'],
            'start_date' => $row['start_date'],
            'paid_count' => $row['paid_count'] ?? 0,
            'installment_notes' => $row['installment_notes'],
        ];

        $rules = [
            'installment_name' => ['nullable', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'months' => ['required', 'integer', 'min:1', 'max:120'],
            'start_date' => ['required', 'date'],
            'paid_count' => ['nullable', 'integer', 'min:0', 'lte:months'],
            'installment_notes' => ['nullable', 'string', 'max:2000'],
        ];

        $validator = Validator::make($payload, $rules, [], $this->attributeNames());

        if ($row['start_date'] === null && $row['start_date_raw'] !== null) {
            $validator->after(function ($v) {
                $v->errors()->add('start_date', 'تاريخ أول قسط غير صالح.');
            });
        }

        $error = $validator->fails()
            ? implode('، ', $validator->errors()->all())
            : null;

        return [
            'line' => $row['line'],
            'valid' => $error === null,
            'error' => $error,
            'has_installment' => true,
            'installment' => [
                'name' => $row['installment_name'],
                'total_amount' => $row['total_amount'] !== null ? (float) $row['total_amount'] : null,
                'months' => $row['months'] !== null ? (int) $row['months'] : null,
                'start_date' => $row['start_date'],
                'paid_count' => $row['paid_count'] !== null ? (int) $row['paid_count'] : 0,
                'notes' => $row['installment_notes'],
            ],
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

    /**
     * Build a normalized parsed row from raw cell values.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeRow(array $raw, int $rowNumber, string $type): array
    {
        if ($type === self::TYPE_INSTALLMENTS) {
            return [
                'line' => $rowNumber,
                'installment_name' => $this->str($raw['installment_name']),
                'total_amount' => $this->numeric($raw['total_amount']),
                'months' => $this->numeric($raw['months']),
                'start_date' => $this->normalizeDate($raw['start_date']),
                'start_date_raw' => $this->str($raw['start_date']),
                'paid_count' => $this->numeric($raw['paid_count']),
                'installment_notes' => $this->str($raw['installment_notes']),
            ];
        }

        return [
            'line' => $rowNumber,
            'name' => $this->str($raw['name']),
            'national_id' => $this->str($raw['national_id'] ?? null),
            'phone' => $this->str($raw['phone']),
            'email' => $this->str($raw['email']),
            'address' => $this->str($raw['address']),
            'job' => $this->str($raw['job'] ?? null),
            'monthly_salary' => $this->numeric($raw['monthly_salary'] ?? null),
            'customer_notes' => $this->str($raw['customer_notes']),
            'guarantor_name' => $this->str($raw['guarantor_name'] ?? null),
            'guarantor_national_id' => $this->str($raw['guarantor_national_id'] ?? null),
            'guarantor_phone' => $this->str($raw['guarantor_phone'] ?? null),
            'installment_name' => $this->str($raw['installment_name']),
            'total_amount' => $this->numeric($raw['total_amount']),
            'months' => $this->numeric($raw['months']),
            'start_date' => $this->normalizeDate($raw['start_date']),
            'start_date_raw' => $this->str($raw['start_date']),
            'paid_count' => $this->numeric($raw['paid_count']),
            'installment_notes' => $this->str($raw['installment_notes']),
        ];
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     */
    private function applyValidations(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $columns): void
    {
        $rangeEnd = self::MAX_ROWS + 1;

        $amount = $this->letterForKey($columns, 'total_amount');
        $salary = $this->letterForKey($columns, 'monthly_salary');
        $months = $this->letterForKey($columns, 'months');
        $paid = $this->letterForKey($columns, 'paid_count');

        if ($amount !== null) {
            for ($row = 2; $row <= $rangeEnd; $row++) {
                $v = $sheet->getCell($amount.$row)->getDataValidation();
                $v->setType(DataValidation::TYPE_DECIMAL);
                $v->setErrorStyle(DataValidation::STYLE_STOP);
                $v->setAllowBlank(true);
                $v->setShowInputMessage(true);
                $v->setShowErrorMessage(true);
                $v->setErrorTitle('قيمة غير صالحة');
                $v->setError('أدخل مبلغاً أكبر من صفر.');
                $v->setOperator(DataValidation::OPERATOR_GREATERTHAN);
                $v->setFormula1('0');
            }
        }

        if ($salary !== null) {
            for ($row = 2; $row <= $rangeEnd; $row++) {
                $v = $sheet->getCell($salary.$row)->getDataValidation();
                $v->setType(DataValidation::TYPE_DECIMAL);
                $v->setErrorStyle(DataValidation::STYLE_STOP);
                $v->setAllowBlank(true);
                $v->setShowInputMessage(true);
                $v->setShowErrorMessage(true);
                $v->setErrorTitle('مرتب غير صالح');
                $v->setError('أدخل مرتباً شهرياً صفراً أو أكثر.');
                $v->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
                $v->setFormula1('0');
            }
        }

        if ($months !== null) {
            for ($row = 2; $row <= $rangeEnd; $row++) {
                $v = $sheet->getCell($months.$row)->getDataValidation();
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
        }

        if ($paid !== null) {
            for ($row = 2; $row <= $rangeEnd; $row++) {
                $v = $sheet->getCell($paid.$row)->getDataValidation();
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
    }

    /**
     * Write the single illustrative example row (row 2) and style it distinctly.
     *
     * @param  array<int, array{key: string, label: string}>  $columns
     */
    private function writeExampleRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $columns, string $type): void
    {
        $example = $type === self::TYPE_INSTALLMENTS ? self::EXAMPLE_INSTALLMENTS : self::EXAMPLE_CUSTOMERS;
        $lastColumn = $this->columnLetter(count($columns) - 1);

        foreach ($columns as $index => $column) {
            $letter = $this->columnLetter($index);
            $key = $column['key'];
            if (! array_key_exists($key, $example)) {
                continue;
            }

            $value = $example[$key];

            // Keep phone + date as text so the example reads exactly as stored.
            if (in_array($key, ['phone', 'national_id', 'guarantor_national_id', 'guarantor_phone', 'start_date'], true)) {
                $sheet->setCellValueExplicit($letter.'2', (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue($letter.'2', $value);
            }
        }

        $sheet->getStyle('A2:'.$lastColumn.'2')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '8D6E63']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E1']],
        ]);

        $comment = $sheet->getComment('A2');
        $comment->getText()->createText('هذا صف مثال توضيحي. احذفه قبل الرفع أو استبدله ببياناتك، وابدأ بإدخال بياناتك من الصف التالي.');
        $comment->setWidth('220pt');
        $comment->setHeight('80pt');
    }

    private function buildInstructionsSheet(Spreadsheet $spreadsheet, string $type): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->safeTitle('تعليمات'));
        $sheet->setRightToLeft(true);

        $sheet->setCellValue('A1', 'تعليمات تعبئة ملف الاستيراد');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        if ($type === self::TYPE_INSTALLMENTS) {
            $lines = [
                ['العمود', 'الوصف'],
                ['اسم القسط / المنتج', 'اختياري. اسم القسط أو المنتج.'],
                ['إجمالي المبلغ *', 'إجباري. رقم أكبر من صفر.'],
                ['عدد الشهور *', 'إجباري. رقم صحيح بين 1 و 120.'],
                ['تاريخ أول قسط *', 'إجباري. مثال: 2026-01-01.'],
                ['عدد الأقساط المدفوعة', 'اختياري. القيمة الافتراضية 0. يعلّم أول N قسط كمدفوع.'],
                ['ملاحظات القسط', 'اختياري.'],
                [],
                ['ملاحظة:', 'جميع الأقساط في هذا الملف تُضاف للعميل الذي تختاره قبل الرفع.'],
                ['تنبيه:', 'الصف الأول في ورقة البيانات هو صف مثال — احذفه أو استبدله ببياناتك.'],
            ];
        } else {
            $lines = [
                ['العمود', 'الوصف'],
                ['اسم العميل *', 'إجباري. اسم العميل الكامل.'],
                ['الرقم القومي', 'اختياري. 14 رقماً. إن وُجد يُستخدم لمعرفة إن كان العميل مسجّلاً من قبل.'],
                ['رقم الهاتف', 'مطلوب إذا لم يُدخل الرقم القومي. يُستخدم للتمييز عند غياب الرقم القومي.'],
                ['البريد الإلكتروني', 'اختياري.'],
                ['العنوان', 'اختياري.'],
                ['الوظيفة', 'اختياري.'],
                ['ملاحظات العميل', 'اختياري.'],
                ['اسم الضامن', 'اختياري.'],
                ['الرقم القومي للضامن', 'اختياري. 14 رقماً.'],
                ['هاتف الضامن', 'اختياري.'],
                ['اسم القسط / المنتج', 'اختياري. اتركه فارغاً لتسجيل العميل فقط بدون قسط.'],
                ['إجمالي المبلغ', 'إجباري عند وجود قسط. رقم أكبر من صفر.'],
                ['عدد الشهور', 'إجباري عند وجود قسط. رقم صحيح بين 1 و 120.'],
                ['تاريخ أول قسط', 'إجباري عند وجود قسط. مثال: 2026-01-15.'],
                ['عدد الأقساط المدفوعة', 'اختياري. القيمة الافتراضية 0. يعلّم أول N قسط كمدفوع.'],
                ['ملاحظات القسط', 'اختياري.'],
                [],
                ['تنبيه:', 'الصف الأول في ورقة البيانات هو صف مثال — احذفه أو استبدله ببياناتك.'],
            ];
        }

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

    private function normalizeType(string $type): string
    {
        return $type === self::TYPE_INSTALLMENTS ? self::TYPE_INSTALLMENTS : self::TYPE_CUSTOMERS;
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function columnsFor(string $type): array
    {
        return $type === self::TYPE_INSTALLMENTS ? self::COLUMNS_INSTALLMENTS : self::COLUMNS_CUSTOMERS;
    }

    private function dataSheetFor(string $type): string
    {
        return $type === self::TYPE_INSTALLMENTS ? self::DATA_SHEET_INSTALLMENTS : self::DATA_SHEET_CUSTOMERS;
    }

    /**
     * Locate the spreadsheet column letter for a given column key.
     *
     * @param  array<int, array{key: string, label: string}>  $columns
     */
    private function letterForKey(array $columns, string $key): ?string
    {
        foreach ($columns as $index => $column) {
            if ($column['key'] === $key) {
                return $this->columnLetter($index);
            }
        }

        return null;
    }

    /**
     * Drop leftover template example rows before preview or save.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function withoutExampleRows(array $rows, string $type): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row) => ! $this->isExampleRow($row, $type)
        ));
    }

    /**
     * Whether a row is the leftover template example (Excel may change types).
     *
     * @param  array<string, mixed>  $row
     */
    private function isExampleRow(array $row, string $type): bool
    {
        if ($type === self::TYPE_INSTALLMENTS) {
            $example = self::EXAMPLE_INSTALLMENTS;
            $nameMatch = $this->compareText($row['installment_name'] ?? null)
                === $this->compareText($example['installment_name']);
            $amountMatch = $this->compareText($row['total_amount'] ?? null)
                === $this->compareText($example['total_amount']);
            $monthsMatch = $this->compareText($row['months'] ?? null)
                === $this->compareText($example['months']);
            $dateMatch = ($row['start_date'] ?? null) === $example['start_date']
                || $this->compareText($row['start_date_raw'] ?? null) === $this->compareText($example['start_date']);
            $notesMatch = $this->compareText($row['installment_notes'] ?? null)
                === $this->compareText($example['installment_notes']);

            return $nameMatch && $amountMatch && $monthsMatch && ($dateMatch || $notesMatch);
        }

        $example = self::EXAMPLE_CUSTOMERS;
        $nameMatch = $this->compareText($row['name'] ?? null) === $this->compareText($example['name']);
        $emailMatch = $this->compareText($row['email'] ?? null) === $this->compareText($example['email']);
        $notes = $this->compareText($row['customer_notes'] ?? null);
        $notesMatch = $notes !== '' && str_contains($notes, 'صف مثال');
        $phoneMatch = PhoneHelper::normalize((string) ($row['phone'] ?? ''))
            === PhoneHelper::normalize((string) $example['phone']);

        return $nameMatch && ($emailMatch || $notesMatch || $phoneMatch);
    }

    private function compareText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        return trim((string) $value);
    }

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

    private function numeric(mixed $value): int|float|null|string
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
            'national_id' => 'الرقم القومي',
            'address' => 'العنوان',
            'job' => 'الوظيفة',
            'monthly_salary' => 'المرتب الشهري',
            'customer_notes' => 'ملاحظات العميل',
            'guarantor_name' => 'اسم الضامن',
            'guarantor_national_id' => 'الرقم القومي للضامن',
            'guarantor_phone' => 'هاتف الضامن',
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
