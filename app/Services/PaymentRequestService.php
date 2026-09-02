<?php

namespace App\Services;

use App\Enums\PaymentRequestStatus;
use App\Models\ClientAccount;
use App\Models\InstallmentItem;
use App\Models\PaymentRequest;
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

        return $paymentRequest->load(['installment', 'installmentItem', 'vendor', 'clientAccount']);
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
            ->with(['clientAccount', 'installment.customer', 'installmentItem', 'vendor'])
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
            ->with(['installment.customer', 'installmentItem', 'vendor'])
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
            ]
        );
    }
}
