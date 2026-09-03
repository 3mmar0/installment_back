<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentRequestResource;
use App\Http\Traits\ApiResponse;
use App\Models\ClientAccount;
use App\Models\PaymentRequest;
use App\Services\PaymentRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientPaymentRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PaymentRequestService $paymentRequestService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'installment_item_id' => ['required', 'integer', 'exists:installment_items,id'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
        ]);

        /** @var ClientAccount $client */
        $client = $request->user();

        try {
            $paymentRequest = $this->paymentRequestService->create(
                $client,
                $data,
                $request->file('attachment')
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'تعذر إرسال طلب الدفع';

            return $this->errorResponse($message, 422, $e->errors());
        }

        return $this->createdResponse(
            new PaymentRequestResource($paymentRequest),
            'تم إرسال طلب الدفع بنجاح، بانتظار تأكيد البائع'
        );
    }

    public function resubmit(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
        ]);

        /** @var ClientAccount $client */
        $client = $request->user();
        $paymentRequest = PaymentRequest::findOrFail($id);

        try {
            $paymentRequest = $this->paymentRequestService->resubmit(
                $paymentRequest,
                $client,
                $data,
                $request->file('attachment')
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'تعذر إعادة إرسال الطلب';

            return $this->errorResponse($message, 422, $e->errors());
        }

        return $this->successResponse(
            new PaymentRequestResource($paymentRequest),
            'تم إعادة إرسال طلب الدفع بنجاح، بانتظار تأكيد البائع'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'in:all,pending,approved,rejected'],
        ]);

        /** @var ClientAccount $client */
        $client = $request->user();

        $requests = $this->paymentRequestService->listForClient($client, $validated);

        return $this->successResponse(
            PaymentRequestResource::collection($requests)->response()->getData(true),
            'تم جلب طلبات الدفع بنجاح'
        );
    }

    public function attachment(int $id, Request $request): StreamedResponse|JsonResponse
    {
        $paymentRequest = PaymentRequest::findOrFail($id);

        /** @var ClientAccount $client */
        $client = $request->user();

        if (! $this->paymentRequestService->authorizeAttachmentAccess($paymentRequest, $client)) {
            return $this->forbiddenResponse('غير مصرح بعرض هذا المرفق');
        }

        return $this->paymentRequestService->streamAttachment($paymentRequest);
    }
}
