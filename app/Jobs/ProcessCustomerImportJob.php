<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\User;
use App\Services\ImportService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessCustomerImportJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /** One attempt only: re-running a partial import would duplicate rows. */
    public int $tries = 1;

    /** Stay comfortably under the worker's 120s timeout. */
    public int $timeout = 110;

    public function __construct(
        public string $batchId,
    ) {}

    public function handle(ImportService $importService, NotificationService $notificationService): void
    {
        $batch = ImportBatch::find($this->batchId);

        if (! $batch || $batch->status !== ImportBatch::STATUS_QUEUED) {
            return;
        }

        $user = User::find($batch->user_id);
        if (! $user) {
            $this->markFailed($batch, 'المستخدم غير موجود.');

            return;
        }

        $type = $batch->type ?: 'customers';
        $rows = $this->resolveRows($batch, $importService, $type);

        if ($rows === false) {
            $this->markFailed($batch, 'إصدار النموذج غير مدعوم. يرجى تنزيل أحدث نموذج.');

            return;
        }

        if ($rows === null) {
            $this->markFailed($batch, 'تعذر العثور على الملف المرفوع. يرجى رفع الملف مرة أخرى.');

            return;
        }

        $batch->update(['status' => ImportBatch::STATUS_PROCESSING]);

        try {
            $result = $importService->import(
                $rows,
                $user,
                function (int $processed, int $total) use ($batch) {
                    $batch->forceFill([
                        'processed_rows' => $processed,
                        'total_rows' => $total,
                    ])->save();
                },
                $type,
                $batch->customer_id,
            );

            $batch->update([
                'status' => ImportBatch::STATUS_COMPLETED,
                'total_rows' => $result['total_rows'],
                'processed_rows' => $result['total_rows'],
                'imported_count' => $result['imported_count'],
                'failed_count' => $result['failed_count'],
                'created_customers' => $result['created_customers'],
                'matched_customers' => $result['matched_customers'],
                'report' => [
                    'failed' => $result['failed'],
                    'warnings' => $result['warnings'],
                ],
                'payload' => null,
            ]);

            $this->notifySummary($notificationService, $user, $result, $batch->type ?: 'customers');
        } catch (\Throwable $e) {
            Log::error('Customer import job failed', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);
            $this->markFailed($batch, 'حدث خطأ أثناء معالجة الملف.');
        } finally {
            $this->cleanupFile($batch);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $batch = ImportBatch::find($this->batchId);
        if ($batch && ! $batch->isFinished()) {
            $this->markFailed($batch, 'تعذر إكمال عملية الاستيراد.');
            $this->cleanupFile($batch);
        }
    }

    private function notifySummary(NotificationService $notificationService, User $user, array $result, string $type): void
    {
        try {
            if ($type === 'installments') {
                $title = 'اكتمل استيراد الأقساط';
                $body = sprintf(
                    'تم استيراد %d قسط. الصفوف التي تعذّر استيرادها: %d.',
                    $result['imported_count'],
                    $result['failed_count']
                );
            } else {
                $title = 'اكتمل استيراد العملاء';
                $body = sprintf(
                    'تم استيراد %d قسط و%d عميل جديد. الصفوف التي تعذّر استيرادها: %d.',
                    $result['imported_count'],
                    $result['created_customers'],
                    $result['failed_count']
                );
            }

            $notificationService->create(
                $user,
                'import_completed',
                $title,
                $body,
                ['type' => $type === 'installments' ? 'installment_import' : 'customer_import'],
                enforceLimits: false
            );
        } catch (\Throwable $e) {
            Log::warning('Import finished but summary notification failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Prefer rows saved during preview. Re-parse the uploaded file only when
     * the worker can still see it (tests / same-process queue).
     *
     * @return array<int, array<string, mixed>>|false|null  false = bad template
     */
    private function resolveRows(ImportBatch $batch, ImportService $importService, string $type): array|false|null
    {
        if ($batch->hasStoredRows()) {
            return $batch->payload;
        }

        if (! $batch->file_path || ! Storage::disk('local')->exists($batch->file_path)) {
            return null;
        }

        $parsed = $importService->parse(Storage::disk('local')->path($batch->file_path), $type);

        return $parsed['version_ok'] ? $parsed['rows'] : false;
    }

    private function markFailed(ImportBatch $batch, string $message): void
    {
        $batch->update([
            'status' => ImportBatch::STATUS_FAILED,
            'error' => $message,
            'payload' => null,
        ]);
    }

    private function cleanupFile(ImportBatch $batch): void
    {
        if ($batch->file_path && Storage::disk('local')->exists($batch->file_path)) {
            Storage::disk('local')->delete($batch->file_path);
            $batch->update(['file_path' => null]);
        }
    }
}
