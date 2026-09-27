<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportFileRequest;
use App\Http\Traits\ApiResponse;
use App\Jobs\ProcessCustomerImportJob;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ImportService $importService,
    ) {}

    /**
     * Download the fixed import template. Public: the file carries no data.
     */
    public function template(): \Symfony\Component\HttpFoundation\Response
    {
        $spreadsheet = $this->importService->buildTemplate();

        $tmp = tempnam(sys_get_temp_dir(), 'tpl');
        if ($tmp === false) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر إنشاء النموذج',
            ], 500);
        }

        try {
            (new Xlsx($spreadsheet))->save($tmp);
            $binary = file_get_contents($tmp);
        } finally {
            @unlink($tmp);
            $spreadsheet->disconnectWorksheets();
        }

        if ($binary === false) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر قراءة النموذج',
            ], 500);
        }

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="customers_import_template.xlsx"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Store the uploaded file, analyze it, and return a preview without saving anything.
     */
    public function preview(ImportFileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $file = $request->file('file');
        $path = $file->store('imports', 'local');

        $parsed = $this->importService->parse(Storage::disk('local')->path($path));

        if (! $parsed['version_ok']) {
            Storage::disk('local')->delete($path);

            return $this->errorResponse(
                'إصدار النموذج غير مدعوم. يرجى تنزيل أحدث نموذج قبل الرفع.',
                422
            );
        }

        $prepared = $this->importService->prepare($parsed['rows'], $user);

        $batch = ImportBatch::create([
            'user_id' => $user->id,
            'type' => 'customers',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'status' => ImportBatch::STATUS_PREVIEWED,
            'total_rows' => $prepared['summary']['total_rows'],
        ]);

        return $this->successResponse([
            'batch_id' => $batch->id,
            'summary' => $prepared['summary'],
            'errors' => $prepared['errors'],
            'warnings' => $prepared['warnings'],
            'truncated' => $parsed['truncated'],
        ], 'تم فحص الملف بنجاح');
    }

    /**
     * Queue the actual import for a previously previewed batch.
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $batch = ImportBatch::where('id', $validated['batch_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $batch) {
            return $this->notFoundResponse('عملية الاستيراد غير موجودة.');
        }

        if ($batch->status !== ImportBatch::STATUS_PREVIEWED) {
            return $this->errorResponse('تم تأكيد هذه العملية بالفعل.', 409);
        }

        $batch->update(['status' => ImportBatch::STATUS_QUEUED]);

        ProcessCustomerImportJob::dispatch($batch->id);

        return $this->successResponse([
            'batch_id' => $batch->id,
            'status' => $batch->status,
        ], 'تم بدء عملية الاستيراد', 202);
    }

    /**
     * Report the live status/progress of a batch.
     */
    public function status(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $batch = ImportBatch::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $batch) {
            return $this->notFoundResponse('عملية الاستيراد غير موجودة.');
        }

        $payload = [
            'batch_id' => $batch->id,
            'status' => $batch->status,
            'percent' => $batch->percent(),
            'total_rows' => $batch->total_rows,
            'processed_rows' => $batch->processed_rows,
            'imported_count' => $batch->imported_count,
            'failed_count' => $batch->failed_count,
            'created_customers' => $batch->created_customers,
            'matched_customers' => $batch->matched_customers,
            'error' => $batch->error,
        ];

        if ($batch->status === ImportBatch::STATUS_COMPLETED) {
            $payload['report'] = $batch->report;
        }

        return $this->successResponse($payload, 'تم جلب حالة الاستيراد');
    }
}
