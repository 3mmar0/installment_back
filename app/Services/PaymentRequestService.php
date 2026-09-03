<?php

namespace App\Services;

use App\Enums\PaymentRequestStatus;
use App\Models\ClientAccount;
use App\Models\InstallmentItem;
use App\Models\PaymentRequest;
use App\Models\PaymentRequestLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaymentRequestService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * @param  array{installment_item_id: int, paid_on: string, note?: string}  $data
     */
    public function create(ClientAccount $client, array $data, UploadedFile $attachment): PaymentRequest
    {
        $item = InstallmentItem::with(['installment.customer'])
            ->findOrFail($data['installment_item_id']);

        $installment = $item->installment;

        if ($installment?->isPersonal()) {
            throw ValidationException::withMessages([
                'installment_item_id' => ['الأقساط الشخصية تُسجَّل مباشرة من صفحة القسط دون طلب موافقة'],
            ]);
        }

        $customer = $installment?->customer;

        if (! $customer || (int) $customer->client_account_id !== (int) $client->id) {
            throw ValidationException::withMessages([
                'installment_item_id' => ['هذا القسط غير مرتبط بحسابك'],
            ]);
        }

        if ($item->status === 'paid' || $item->paid_at) {
            throw ValidationException::withMessages([
                'installment_item_id' => ['هذه الدفعة مسددة مسبقاً'],
            ]);
        }

        $existingPending = PaymentRequest::query()
            ->where('pending_item_id', $item->id)
            ->exists();

        if ($existingPending) {
            throw ValidationException::withMessages([
                'installment_item_id' => ['يوجد طلب دفع قيد المراجعة لهذه الدفعة'],
            ]);
        }

        $existingRejected = PaymentRequest::query()
            ->where('installment_item_id', $item->id)
            ->where('client_account_id', $client->id)
            ->where('status', PaymentRequestStatus::Rejected)
            ->first();

        if ($existingRejected) {
            throw ValidationException::withMessages([
                'installment_item_id' => [
                    'يوجد طلب مرفوض لهذه الدفعة. يرجى إعادة إرسال الطلب #'.$existingRejected->id,
                ],
                'payment_request_id' => [(string) $existingRejected->id],
            ]);
        }

        $path = $attachment->store(
            'payment-proofs/'.$client->id,
            'local'
        );

        $paymentRequest = PaymentRequest::create([
            'installment_item_id' => $item->id,
            'installment_id' => $installment->id,
            'client_account_id' => $client->id,
            'user_id' => $installment->user_id,
            'amount' => $item->amount,
            'paid_on' => $data['paid_on'],
            'note' => $data['note'] ?? null,
            'attachment_path' => $path,
            'attachment_mime' => $attachment->getMimeType(),
            'attachment_size' => $attachment->getSize(),
            'status' => PaymentRequestStatus::Pending,
            'pending_item_id' => $item->id,
        ]);

        $this->recordLog($paymentRequest, PaymentRequestLog::ACTION_SUBMITTED, [
            'actor_client_id' => $client->id,
        ]);

        $vendor = User::find($installment->user_id);
        if ($vendor) {
            $amountFormatted = number_format((float) $item->amount, 2).' ج.م';
            $clientName = $client->name ?: $customer->name;

            $this->notificationService->create(
                $vendor,
                'payment_request',
                'طلب تأكيد دفع جديد',
                "أرسل العميل {$clientName} طلب تأكيد دفع بقيمة {$amountFormatted}",
                [
                    'payment_request_id' => $paymentRequest->id,
                    'installment_id' => $installment->id,
                    'item_id' => $item->id,
                    'amount' => (float) $item->amount,
                    'client_name' => $clientName,
                ],
                enforceLimits: false
            );
        }

        return $paymentRequest->load(['installment', 'installmentItem', 'vendor', 'clientAccount', 'logs']);
    }

    /**
     * @param  array{paid_on: string, note?: string}  $data
     */
    public function resubmit(
        PaymentRequest $paymentRequest,
        ClientAccount $client,
        array $data,
        UploadedFile $attachment
    ): PaymentRequest {
        if ((int) $paymentRequest->client_account_id !== (int) $client->id) {
            abort(403, 'غير مصرح بإعادة إرسال هذا الطلب');
        }

        if ($paymentRequest->status !== PaymentRequestStatus::Rejected) {
            throw ValidationException::withMessages([
                'status' => ['يمكن إعادة إرسال الطلبات المرفوضة فقط'],
            ]);
        }

        $item = InstallmentItem::with(['installment.customer'])
            ->findOrFail($paymentRequest->installment_item_id);

        if ($item->status === 'paid' || $item->paid_at) {
            throw ValidationException::withMessages([
                'status' => ['هذه الدفعة مسددة مسبقاً'],
            ]);
        }

        $existingPending = PaymentRequest::query()
            ->where('pending_item_id', $item->id)
            ->where('id', '!=', $paymentRequest->id)
            ->exists();

        if ($existingPending) {
            throw ValidationException::withMessages([
                'status' => ['يوجد طلب دفع قيد المراجعة لهذه الدفعة'],
            ]);
        }

        return DB::transaction(function () use ($paymentRequest, $client, $data, $attachment, $item) {
            $this->recordLog($paymentRequest, PaymentRequestLog::ACTION_RESUBMITTED, [
                'actor_client_id' => $client->id,
                'rejection_reason' => $paymentRequest->rejection_reason,
            ]);

            if ($paymentRequest->attachment_path) {
                Storage::disk('local')->delete($paymentRequest->attachment_path);
            }

            $path = $attachment->store(
                'payment-proofs/'.$client->id,
                'local'
            );

            $paymentRequest->update([
                'paid_on' => $data['paid_on'],
                'note' => $data['note'] ?? null,
                'attachment_path' => $path,
                'attachment_mime' => $attachment->getMimeType(),
                'attachment_size' => $attachment->getSize(),
                'status' => PaymentRequestStatus::Pending,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                'pending_item_id' => $item->id,
            ]);

            $installment = $item->installment;
            $vendor = User::find($paymentRequest->user_id);
            if ($vendor) {
                $amountFormatted = number_format((float) $paymentRequest->amount, 2).' ج.م';
                $clientName = $client->name ?: $installment?->customer?->name;

                $this->notificationService->create(
                    $vendor,
                    'payment_request',
                    'إعادة إرسال طلب تأكيد دفع',
                    "أعاد العميل {$clientName} إرسال طلب الدفع #{$paymentRequest->id} بقيمة {$amountFormatted}",
                    [
                        'payment_request_id' => $paymentRequest->id,
                        'installment_id' => $paymentRequest->installment_id,
                        'item_id' => $paymentRequest->installment_item_id,
                        'amount' => (float) $paymentRequest->amount,
                        'client_name' => $clientName,
                        'resubmitted' => true,
                    ],
                    enforceLimits: false
                );
            }

            return $paymentRequest->fresh([
                'installment',
                'installmentItem',
                'vendor',
                'clientAccount',
                'logs',
            ]);
        });
    }

    public function approve(PaymentRequest $paymentRequest, User $vendor): PaymentRequest
    {
        if ((int) $paymentRequest->user_id !== (int) $vendor->id && ! $vendor->isOwner()) {
            abort(403, 'غير مصرح بمراجعة هذا الطلب');
        }

        if (! $paymentRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن اعتماد طلب غير قيد المراجعة'],
            ]);
        }

        return DB::transaction(function () use ($paymentRequest, $vendor) {
            $item = InstallmentItem::lockForUpdate()->findOrFail($paymentRequest->installment_item_id);

            if ($item->status === 'paid' || $item->paid_at) {
                throw ValidationException::withMessages([
                    'status' => ['هذه الدفعة مسددة مسبقاً'],
                ]);
            }

            $item->markPaid(
                (float) $item->amount,
                'PR-'.$paymentRequest->id,
                $paymentRequest->note
            );

            $paymentRequest->update([
                'status' => PaymentRequestStatus::Approved,
                'reviewed_by' => $vendor->id,
                'reviewed_at' => now(),
                'pending_item_id' => null,
            ]);

            $this->recordLog($paymentRequest, PaymentRequestLog::ACTION_APPROVED, [
                'actor_user_id' => $vendor->id,
            ]);

            $client = $paymentRequest->clientAccount;
            if ($client) {
                $amountFormatted = number_format((float) $paymentRequest->amount, 2).' ج.م';
                $this->notificationService->createForClient(
                    $client,
                    'payment_request_approved',
                    'تم اعتماد الدفعة',
                    "تم اعتماد طلب الدفع بقيمة {$amountFormatted}",
                    [
                        'payment_request_id' => $paymentRequest->id,
                        'installment_id' => $paymentRequest->installment_id,
                        'item_id' => $paymentRequest->installment_item_id,
                        'amount' => (float) $paymentRequest->amount,
                    ]
                );
            }

            return $paymentRequest->fresh([
                'installment',
                'installmentItem',
                'vendor',
                'clientAccount',
            ]);
        });
    }

    public function reject(PaymentRequest $paymentRequest, User $vendor, string $reason): PaymentRequest
    {
        if ((int) $paymentRequest->user_id !== (int) $vendor->id && ! $vendor->isOwner()) {
            abort(403, 'غير مصرح بمراجعة هذا الطلب');
        }

        if (! $paymentRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن رفض طلب غير قيد المراجعة'],
            ]);
        }

        $paymentRequest->update([
            'status' => PaymentRequestStatus::Rejected,
            'reviewed_by' => $vendor->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
            'pending_item_id' => null,
        ]);

        $this->recordLog($paymentRequest, PaymentRequestLog::ACTION_REJECTED, [
            'actor_user_id' => $vendor->id,
            'rejection_reason' => $reason,
        ]);

        $client = $paymentRequest->clientAccount;
        if ($client) {
            $amountFormatted = number_format((float) $paymentRequest->amount, 2).' ج.م';
            $this->notificationService->createForClient(
                $client,
                'payment_request_rejected',
                'تم رفض طلب الدفع',
                "تم رفض طلب الدفع بقيمة {$amountFormatted}: {$reason}",
                [
                    'payment_request_id' => $paymentRequest->id,
                    'installment_id' => $paymentRequest->installment_id,
                    'item_id' => $paymentRequest->installment_item_id,
                    'amount' => (float) $paymentRequest->amount,
                    'rejection_reason' => $reason,
                ]
            );
        }

        return $paymentRequest->fresh([
            'installment',
            'installmentItem',
            'vendor',
            'clientAccount',
        ]);
    }

    /**
     * @param  array{page?: int, per_page?: int, status?: string}  $filters
     */
    public function listForVendor(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $status = $filters['status'] ?? null;

        $query = PaymentRequest::query()
            ->with(['clientAccount', 'installment.customer', 'installmentItem', 'vendor', 'logs'])
            ->when(! $user->isOwner(), fn ($q) => $q->where('user_id', $user->id))
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('id');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array{page?: int, per_page?: int, status?: string}  $filters
     */
    public function listForClient(ClientAccount $client, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $status = $filters['status'] ?? null;

        $query = PaymentRequest::query()
            ->with(['installment.customer', 'installmentItem', 'vendor', 'logs'])
            ->where('client_account_id', $client->id)
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function pendingCountForVendor(User $user): int
    {
        return PaymentRequest::query()
            ->where('status', PaymentRequestStatus::Pending)
            ->when(! $user->isOwner(), fn ($q) => $q->where('user_id', $user->id))
            ->count();
    }

    public function authorizeAttachmentAccess(PaymentRequest $paymentRequest, User|ClientAccount $actor): bool
    {
        if ($actor instanceof ClientAccount) {
            return (int) $paymentRequest->client_account_id === (int) $actor->id;
        }

        if ($actor->isOwner()) {
            return true;
        }

        return (int) $paymentRequest->user_id === (int) $actor->id;
    }

    public function streamAttachment(PaymentRequest $paymentRequest)
    {
        if (! Storage::disk('local')->exists($paymentRequest->attachment_path)) {
            abort(404, 'الملف غير موجود');
        }

        return Storage::disk('local')->response(
            $paymentRequest->attachment_path,
            basename($paymentRequest->attachment_path),
            [
                'Content-Type' => $paymentRequest->attachment_mime ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.basename($paymentRequest->attachment_path).'"',
            ]
        );
    }

    /**
     * @param  array{
     *     actor_user_id?: int|null,
     *     actor_client_id?: int|null,
     *     rejection_reason?: string|null,
     *     paid_on?: mixed,
     *     note?: string|null,
     *     attachment_path?: string|null,
     *     attachment_mime?: string|null,
     *     attachment_size?: int|null,
     * }  $overrides
     */
    private function recordLog(
        PaymentRequest $paymentRequest,
        string $action,
        array $overrides = []
    ): PaymentRequestLog {
        return PaymentRequestLog::create([
            'payment_request_id' => $paymentRequest->id,
            'action' => $action,
            'paid_on' => $overrides['paid_on'] ?? $paymentRequest->paid_on,
            'note' => array_key_exists('note', $overrides)
                ? $overrides['note']
                : $paymentRequest->note,
            'attachment_path' => $overrides['attachment_path'] ?? $paymentRequest->attachment_path,
            'attachment_mime' => $overrides['attachment_mime'] ?? $paymentRequest->attachment_mime,
            'attachment_size' => $overrides['attachment_size'] ?? $paymentRequest->attachment_size,
            'rejection_reason' => $overrides['rejection_reason'] ?? null,
            'actor_user_id' => $overrides['actor_user_id'] ?? null,
            'actor_client_id' => $overrides['actor_client_id'] ?? null,
        ]);
    }
}
