<?php

use App\Helpers\PhoneHelper;
use App\Jobs\ProcessCustomerImportJob;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\Installment;
use App\Models\Notification;
use App\Services\ImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Build a parsed-row array (the shape ImportService::parse produces) so the
 * service logic can be tested without an xlsx round-trip.
 */
function importRow(int $line, array $overrides = []): array
{
    return array_merge([
        'line' => $line,
        'name' => null,
        'phone' => null,
        'email' => null,
        'address' => null,
        'customer_notes' => null,
        'installment_name' => null,
        'total_amount' => null,
        'months' => null,
        'start_date' => null,
        'start_date_raw' => null,
        'paid_count' => null,
        'installment_notes' => null,
    ], $overrides);
}

/**
 * Build a real xlsx binary matching the import template layout.
 *
 * @param  array<int, array<string, mixed>>  $rows  keyed by column key
 */
function buildImportXlsx(array $rows, bool $withVersion = true): string
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('البيانات');

    if ($withVersion) {
        $sheet->setCellValue('N1', ImportService::TEMPLATE_VERSION);
    }

    $columns = ['name', 'phone', 'email', 'address', 'customer_notes',
        'installment_name', 'total_amount', 'months', 'start_date', 'paid_count', 'installment_notes'];

    $rowNumber = 2;
    foreach ($rows as $row) {
        $col = 1;
        foreach ($columns as $key) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                if (in_array($key, ['phone'], true)) {
                    $sheet->setCellValueExplicit($letter.$rowNumber, (string) $row[$key], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($letter.$rowNumber, $row[$key]);
                }
            }
            $col++;
        }
        $rowNumber++;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($spreadsheet))->save($tmp);
    $binary = file_get_contents($tmp);
    @unlink($tmp);
    $spreadsheet->disconnectWorksheets();

    return $binary;
}

/**
 * Build an installments-template xlsx (columns A..F) tied to one chosen customer.
 *
 * @param  array<int, array<string, mixed>>  $rows  keyed by column key
 */
function buildInstallmentsXlsx(array $rows, bool $withVersion = true, bool $withType = true): string
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('الأقساط');

    if ($withVersion) {
        $sheet->setCellValue('N1', ImportService::TEMPLATE_VERSION);
    }
    if ($withType) {
        $sheet->setCellValueExplicit('M1', 'installments', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    }

    $columns = ['installment_name', 'total_amount', 'months', 'start_date', 'paid_count', 'installment_notes'];

    $rowNumber = 2;
    foreach ($rows as $row) {
        $col = 1;
        foreach ($columns as $key) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                $sheet->setCellValue($letter.$rowNumber, $row[$key]);
            }
            $col++;
        }
        $rowNumber++;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($spreadsheet))->save($tmp);
    $binary = file_get_contents($tmp);
    @unlink($tmp);
    $spreadsheet->disconnectWorksheets();

    return $binary;
}

function fakeXlsxUpload(string $binary, string $name = 'customers.xlsx'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $binary);
}

// ---------------------------------------------------------------------------
// Service-level logic
// ---------------------------------------------------------------------------

it('groups rows with the same phone under one customer', function () {
    $merchant = merchantWithPlan();

    $rows = [
        importRow(2, ['name' => 'أحمد', 'phone' => '01000000010', 'installment_name' => 'تلفزيون', 'total_amount' => 6000, 'months' => 6, 'start_date' => '2026-01-01']),
        importRow(3, ['name' => 'أحمد', 'phone' => '01000000010', 'installment_name' => 'ثلاجة', 'total_amount' => 4000, 'months' => 4, 'start_date' => '2026-02-01']),
    ];

    $result = app(ImportService::class)->import($rows, $merchant);

    expect(Customer::where('user_id', $merchant->id)->count())->toBe(1)
        ->and(Installment::count())->toBe(2)
        ->and($result['created_customers'])->toBe(1)
        ->and($result['imported_count'])->toBe(2)
        ->and($result['failed_count'])->toBe(0);
});

it('attaches installments to an existing customer without changing its data', function () {
    $merchant = merchantWithPlan();

    $existing = Customer::factory()->forMerchant($merchant)->create([
        'name' => 'الاسم الأصلي',
        'phone' => '01000000020',
        'phone_normalized' => PhoneHelper::normalize('01000000020'),
    ]);

    $rows = [
        importRow(2, ['name' => 'اسم مختلف', 'phone' => '01000000020', 'installment_name' => 'غسالة', 'total_amount' => 3000, 'months' => 3, 'start_date' => '2026-01-01']),
    ];

    $result = app(ImportService::class)->import($rows, $merchant);

    expect(Customer::where('user_id', $merchant->id)->count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('الاسم الأصلي')
        ->and($result['created_customers'])->toBe(0)
        ->and($result['matched_customers'])->toBe(1)
        ->and(Installment::where('customer_id', $existing->id)->count())->toBe(1)
        ->and($result['warnings'])->not->toBeEmpty();
});

it('reports a bad row while saving the good ones (partial import)', function () {
    $merchant = merchantWithPlan();

    $rows = [
        importRow(2, ['name' => 'صالح', 'phone' => '01000000030', 'installment_name' => 'موبايل', 'total_amount' => 5000, 'months' => 5, 'start_date' => '2026-01-01']),
        importRow(3, ['name' => 'خاطئ', 'phone' => '01000000031', 'installment_name' => 'لابتوب', 'total_amount' => 5000, 'months' => 0, 'start_date' => '2026-01-01']),
    ];

    $result = app(ImportService::class)->import($rows, $merchant);

    expect($result['imported_count'])->toBe(1)
        ->and($result['failed_count'])->toBe(1)
        ->and($result['failed'][0]['line'])->toBe(3)
        ->and(Installment::count())->toBe(1);
});

it('marks the first N items as paid from paid_count', function () {
    $merchant = merchantWithPlan();

    $rows = [
        importRow(2, ['name' => 'مدفوع', 'phone' => '01000000040', 'total_amount' => 6000, 'months' => 6, 'start_date' => '2026-01-01', 'paid_count' => 2]),
    ];

    app(ImportService::class)->import($rows, $merchant);

    $installment = Installment::with('items')->firstOrFail();
    $paid = $installment->items->where('status', 'paid')->sortBy('due_date')->values();

    expect($paid)->toHaveCount(2)
        ->and((float) $paid[0]->paid_amount)->toBe((float) $paid[0]->amount)
        ->and($paid[0]->paid_at->toDateString())->toBe($paid[0]->due_date->toDateString());
});

it('parses excel serial dates and text dates', function () {
    $serial = ExcelDate::PHPToExcel(new DateTime('2026-03-10'));

    $binary = buildImportXlsx([
        ['name' => 'نص', 'phone' => '01000000050', 'total_amount' => 3000, 'months' => 3, 'start_date' => '2026-05-20'],
        ['name' => 'رقم', 'phone' => '01000000051', 'total_amount' => 3000, 'months' => 3, 'start_date' => $serial],
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    file_put_contents($tmp, $binary);

    $parsed = app(ImportService::class)->parse($tmp);
    @unlink($tmp);

    expect($parsed['version_ok'])->toBeTrue()
        ->and($parsed['rows'][0]['start_date'])->toBe('2026-05-20')
        ->and($parsed['rows'][1]['start_date'])->toBe('2026-03-10');
});

it('never matches another merchants customer with the same phone', function () {
    $other = merchantWithPlan();
    $otherCustomer = Customer::factory()->forMerchant($other)->create([
        'name' => 'عميل تاجر آخر',
        'phone' => '01000000060',
        'phone_normalized' => PhoneHelper::normalize('01000000060'),
    ]);

    $merchant = merchantWithPlan();

    $rows = [
        importRow(2, ['name' => 'عميلي', 'phone' => '01000000060', 'total_amount' => 3000, 'months' => 3, 'start_date' => '2026-01-01']),
    ];

    app(ImportService::class)->import($rows, $merchant);

    expect(Customer::where('user_id', $merchant->id)->count())->toBe(1)
        ->and($otherCustomer->fresh()->name)->toBe('عميل تاجر آخر')
        ->and(Installment::where('user_id', $merchant->id)->count())->toBe(1)
        ->and(Installment::where('customer_id', $otherCustomer->id)->count())->toBe(0);
});

it('enforces installment plan limits', function () {
    $merchant = merchantWithPlan(['installments' => ['from' => 0, 'to' => 1]]);

    $rows = [
        importRow(2, ['name' => 'أول', 'phone' => '01000000070', 'total_amount' => 3000, 'months' => 3, 'start_date' => '2026-01-01']),
        importRow(3, ['name' => 'ثاني', 'phone' => '01000000071', 'total_amount' => 3000, 'months' => 3, 'start_date' => '2026-01-01']),
    ];

    $result = app(ImportService::class)->import($rows, $merchant);

    expect(Installment::count())->toBe(1)
        ->and($result['imported_count'])->toBe(1)
        ->and($result['failed_count'])->toBe(1)
        ->and($result['failed'][0]['error'])->toContain('حد الباقة');
});

it('does not send a per-installment notification during import', function () {
    $merchant = merchantWithPlan();

    $rows = [
        importRow(2, ['name' => 'أحمد', 'phone' => '01000000080', 'total_amount' => 6000, 'months' => 6, 'start_date' => '2026-01-01']),
        importRow(3, ['name' => 'سارة', 'phone' => '01000000081', 'total_amount' => 4000, 'months' => 4, 'start_date' => '2026-02-01']),
    ];

    app(ImportService::class)->import($rows, $merchant);

    expect(Notification::where('type', 'installment_created')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Job-level
// ---------------------------------------------------------------------------

it('processes a queued batch to completion with progress and a report', function () {
    Storage::fake('local');
    $merchant = merchantWithPlan();

    $binary = buildImportXlsx([
        ['name' => 'أحمد', 'phone' => '01000000090', 'total_amount' => 6000, 'months' => 6, 'start_date' => '2026-01-01'],
        ['name' => 'خاطئ', 'phone' => '01000000091', 'total_amount' => 6000, 'months' => 0, 'start_date' => '2026-01-01'],
    ]);
    $path = 'imports/batch.xlsx';
    Storage::disk('local')->put($path, $binary);

    $batch = ImportBatch::create([
        'user_id' => $merchant->id,
        'type' => 'customers',
        'file_path' => $path,
        'status' => ImportBatch::STATUS_QUEUED,
        'total_rows' => 2,
    ]);

    ProcessCustomerImportJob::dispatchSync($batch->id);

    $batch->refresh();

    expect($batch->status)->toBe(ImportBatch::STATUS_COMPLETED)
        ->and($batch->processed_rows)->toBe($batch->total_rows)
        ->and($batch->imported_count)->toBe(1)
        ->and($batch->failed_count)->toBe(1)
        ->and($batch->report['failed'])->not->toBeEmpty()
        ->and(Storage::disk('local')->exists($path))->toBeFalse()
        ->and(Notification::where('type', 'import_completed')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// HTTP endpoints
// ---------------------------------------------------------------------------

it('downloads the template as an xlsx attachment without auth', function () {
    $this->get('/api/import/template')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('Content-Disposition', 'attachment; filename="customers_import_template.xlsx"');
});

it('previews then confirms an upload end to end', function () {
    Storage::fake('local');
    $merchant = actingAsMerchant();

    $binary = buildImportXlsx([
        ['name' => 'أحمد', 'phone' => '01000000100', 'total_amount' => 6000, 'months' => 6, 'start_date' => '2026-01-01'],
        ['name' => 'أحمد', 'phone' => '01000000100', 'installment_name' => 'ثلاجة', 'total_amount' => 4000, 'months' => 4, 'start_date' => '2026-02-01'],
    ]);

    $preview = $this->postJson('/api/import/preview', ['file' => fakeXlsxUpload($binary)])
        ->assertOk()
        ->json('data');

    expect($preview['summary']['new_customers'])->toBe(1)
        ->and($preview['summary']['installments'])->toBe(2);

    $this->postJson('/api/import/confirm', ['batch_id' => $preview['batch_id']])
        ->assertStatus(202);

    // Sync queue runs the job inline, so the batch is already completed.
    $this->getJson("/api/import/status/{$preview['batch_id']}")
        ->assertOk()
        ->assertJsonPath('data.status', ImportBatch::STATUS_COMPLETED);

    expect(Customer::where('user_id', $merchant->id)->count())->toBe(1)
        ->and(Installment::where('user_id', $merchant->id)->count())->toBe(2);
});

it('rejects an uploaded file with a wrong template version', function () {
    Storage::fake('local');
    actingAsMerchant();

    $binary = buildImportXlsx([
        ['name' => 'أحمد', 'phone' => '01000000110', 'total_amount' => 6000, 'months' => 6, 'start_date' => '2026-01-01'],
    ], withVersion: false);

    $this->postJson('/api/import/preview', ['file' => fakeXlsxUpload($binary)])
        ->assertStatus(422);
});

it('does not expose another users batch on status or confirm', function () {
    $owner = merchantWithPlan();
    $batch = ImportBatch::create([
        'user_id' => $owner->id,
        'type' => 'customers',
        'status' => ImportBatch::STATUS_PREVIEWED,
        'total_rows' => 1,
    ]);

    actingAsMerchant();

    $this->getJson("/api/import/status/{$batch->id}")->assertStatus(404);
    $this->postJson('/api/import/confirm', ['batch_id' => $batch->id])->assertStatus(404);
});

it('rejects confirming a batch twice', function () {
    Storage::fake('local');
    $merchant = actingAsMerchant();

    $binary = buildImportXlsx([
        ['name' => 'أحمد', 'phone' => '01000000120', 'total_amount' => 6000, 'months' => 6, 'start_date' => '2026-01-01'],
    ]);

    $batchId = $this->postJson('/api/import/preview', ['file' => fakeXlsxUpload($binary)])
        ->assertOk()
        ->json('data.batch_id');

    $this->postJson('/api/import/confirm', ['batch_id' => $batchId])->assertStatus(202);
    $this->postJson('/api/import/confirm', ['batch_id' => $batchId])->assertStatus(409);
});

// ---------------------------------------------------------------------------
// Example row
// ---------------------------------------------------------------------------

it('keeps the built-in example row out of the parsed data', function () {
    $service = app(ImportService::class);

    $tmp = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    (new Xlsx($service->buildTemplate(ImportService::TYPE_CUSTOMERS)))->save($tmp);

    $parsed = $service->parse($tmp, ImportService::TYPE_CUSTOMERS);
    @unlink($tmp);

    expect($parsed['version_ok'])->toBeTrue()
        ->and($parsed['rows'])->toBe([]);
});

it('ignores a leftover example row when saving, even if excel changed types', function () {
    $merchant = merchantWithPlan();

    $rows = [
        importRow(2, [
            'name' => 'أحمد علي',
            'phone' => '1000000000',
            'email' => 'ahmed@example.com',
            'address' => 'القاهرة - مصر الجديدة',
            'customer_notes' => 'صف مثال — احذفه أو استبدله ببياناتك',
            'installment_name' => 'تلفزيون سامسونج',
            'total_amount' => 12000.0,
            'months' => 12,
            'start_date' => '2026-01-01',
            'paid_count' => 2,
            'installment_notes' => 'ملاحظة توضيحية',
        ]),
        importRow(3, [
            'name' => 'عميل حقيقي',
            'phone' => '01099999999',
            'total_amount' => 5000,
            'months' => 5,
            'start_date' => '2026-01-01',
        ]),
    ];

    $result = app(ImportService::class)->import($rows, $merchant);

    expect(Customer::where('user_id', $merchant->id)->count())->toBe(1)
        ->and(Customer::where('user_id', $merchant->id)->value('name'))->toBe('عميل حقيقي')
        ->and($result['imported_count'])->toBe(1)
        ->and($result['failed_count'])->toBe(0);
});

it('imports real rows added below the example row', function () {
    $service = app(ImportService::class);

    $spreadsheet = $service->buildTemplate(ImportService::TYPE_CUSTOMERS);
    $sheet = $spreadsheet->getActiveSheet();
    // Row 2 holds the example; the user types real data on row 3.
    $sheet->setCellValueExplicit('A3', 'عميل حقيقي', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValueExplicit('B3', '01099999999', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('G3', 5000);
    $sheet->setCellValue('H3', 5);
    $sheet->setCellValueExplicit('I3', '2026-01-01', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

    $tmp = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    (new Xlsx($spreadsheet))->save($tmp);
    $parsed = $service->parse($tmp, ImportService::TYPE_CUSTOMERS);
    @unlink($tmp);

    expect($parsed['rows'])->toHaveCount(1)
        ->and($parsed['rows'][0]['line'])->toBe(3)
        ->and($parsed['rows'][0]['name'])->toBe('عميل حقيقي');
});

// ---------------------------------------------------------------------------
// Installments import (attach to a chosen customer)
// ---------------------------------------------------------------------------

it('imports installments onto the chosen customer', function () {
    $merchant = merchantWithPlan();
    $customer = Customer::factory()->forMerchant($merchant)->create([
        'name' => 'عميل الأقساط',
        'phone' => '01000000200',
        'phone_normalized' => PhoneHelper::normalize('01000000200'),
    ]);

    $rows = [
        importRow(2, ['installment_name' => 'تلفزيون', 'total_amount' => 6000, 'months' => 6, 'start_date' => '2026-01-01']),
        importRow(3, ['installment_name' => 'ثلاجة', 'total_amount' => 4000, 'months' => 4, 'start_date' => '2026-02-01', 'paid_count' => 1]),
    ];

    $result = app(ImportService::class)->import($rows, $merchant, null, ImportService::TYPE_INSTALLMENTS, $customer->id);

    expect(Customer::where('user_id', $merchant->id)->count())->toBe(1)
        ->and($result['created_customers'])->toBe(0)
        ->and($result['imported_count'])->toBe(2)
        ->and($result['failed_count'])->toBe(0)
        ->and(Installment::where('customer_id', $customer->id)->count())->toBe(2);
});

it('enforces installment limits for installments import', function () {
    $merchant = merchantWithPlan(['installments' => ['from' => 0, 'to' => 1]]);
    $customer = Customer::factory()->forMerchant($merchant)->create([
        'phone' => '01000000210',
        'phone_normalized' => PhoneHelper::normalize('01000000210'),
    ]);

    $rows = [
        importRow(2, ['total_amount' => 3000, 'months' => 3, 'start_date' => '2026-01-01']),
        importRow(3, ['total_amount' => 3000, 'months' => 3, 'start_date' => '2026-01-01']),
    ];

    $result = app(ImportService::class)->import($rows, $merchant, null, ImportService::TYPE_INSTALLMENTS, $customer->id);

    expect(Installment::count())->toBe(1)
        ->and($result['imported_count'])->toBe(1)
        ->and($result['failed_count'])->toBe(1)
        ->and($result['failed'][0]['error'])->toContain('حد الباقة');
});

it('downloads the installments template with its own filename', function () {
    $this->get('/api/import/template?type=installments')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('Content-Disposition', 'attachment; filename="installments_import_template.xlsx"');
});

it('previews then confirms an installments import end to end', function () {
    Storage::fake('local');
    $merchant = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($merchant)->create([
        'name' => 'عميل الاستيراد',
        'phone' => '01000000220',
        'phone_normalized' => PhoneHelper::normalize('01000000220'),
    ]);

    $binary = buildInstallmentsXlsx([
        ['installment_name' => 'تلفزيون', 'total_amount' => 6000, 'months' => 6, 'start_date' => '2026-01-01'],
        ['installment_name' => 'ثلاجة', 'total_amount' => 4000, 'months' => 4, 'start_date' => '2026-02-01'],
    ]);

    $preview = $this->postJson('/api/import/preview', [
        'file' => fakeXlsxUpload($binary, 'installments.xlsx'),
        'type' => 'installments',
        'customer_id' => $customer->id,
    ])->assertOk()->json('data');

    expect($preview['summary']['installments'])->toBe(2)
        ->and($preview['summary']['customer_name'])->toBe('عميل الاستيراد')
        ->and($preview['summary']['new_customers'])->toBe(0);

    $this->postJson('/api/import/confirm', ['batch_id' => $preview['batch_id']])
        ->assertStatus(202);

    $this->getJson("/api/import/status/{$preview['batch_id']}")
        ->assertOk()
        ->assertJsonPath('data.status', ImportBatch::STATUS_COMPLETED);

    expect(Installment::where('customer_id', $customer->id)->count())->toBe(2);
});

it('rejects an installments import for another merchants customer', function () {
    $other = merchantWithPlan();
    $otherCustomer = Customer::factory()->forMerchant($other)->create([
        'phone' => '01000000230',
        'phone_normalized' => PhoneHelper::normalize('01000000230'),
    ]);

    Storage::fake('local');
    actingAsMerchant();

    $binary = buildInstallmentsXlsx([
        ['installment_name' => 'تلفزيون', 'total_amount' => 6000, 'months' => 6, 'start_date' => '2026-01-01'],
    ]);

    $this->postJson('/api/import/preview', [
        'file' => fakeXlsxUpload($binary, 'installments.xlsx'),
        'type' => 'installments',
        'customer_id' => $otherCustomer->id,
    ])->assertStatus(404);
});

it('requires a customer_id for installments imports', function () {
    Storage::fake('local');
    actingAsMerchant();

    $binary = buildInstallmentsXlsx([
        ['installment_name' => 'تلفزيون', 'total_amount' => 6000, 'months' => 6, 'start_date' => '2026-01-01'],
    ]);

    $this->postJson('/api/import/preview', [
        'file' => fakeXlsxUpload($binary, 'installments.xlsx'),
        'type' => 'installments',
    ])->assertStatus(422);
});

it('rejects a customers template uploaded to the installments flow', function () {
    Storage::fake('local');
    $merchant = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($merchant)->create([
        'phone' => '01000000240',
        'phone_normalized' => PhoneHelper::normalize('01000000240'),
    ]);

    // A normal customers-template file (no installments type marker).
    $binary = buildImportXlsx([
        ['name' => 'أحمد', 'phone' => '01000000240', 'total_amount' => 6000, 'months' => 6, 'start_date' => '2026-01-01'],
    ]);

    $this->postJson('/api/import/preview', [
        'file' => fakeXlsxUpload($binary, 'customers.xlsx'),
        'type' => 'installments',
        'customer_id' => $customer->id,
    ])->assertStatus(422);
});
