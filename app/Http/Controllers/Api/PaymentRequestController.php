<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentRequestResource;
use App\Http\Traits\ApiResponse;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\PaymentRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PaymentRequestService $paymentRequestService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'in:all,pending,approved,rejected'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $requests = $this->paymentRequestService->listForVendor($user, $validated);

        return $this->successResponse(
            PaymentRequestResource::collection($requests)->response()->getData(true),
            'تم جلب طلبات الدفع بنجاح'
        );
    }

    public function pendingCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse([
            'count' => $this->paymentRequestService->pendingCountForVendor($user),
        ], 'تم جلب عدد الطلبات المعلقة');
    }

    public function approve(int $id, Request $request): JsonResponse
    {
        $paymentRequest = PaymentRequest::findOrFail($id);

        try {
            $result = $this->paymentRequestService->approve($paymentRequest, $request->user());
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'تعذر اعتماد الطلب';

            return $this->errorResponse($message, 422, $e->errors());
        }

        return $this->successResponse(
            new PaymentRequestResource($result),
            'تم اعتماد طلب الدفع وتسجيل الدفعة'
        );
    }

    public function reject(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $paymentRequest = PaymentRequest::findOrFail($id);

        try {
            $result = $this->paymentRequestService->reject(
                $paymentRequest,
                $request->user(),
                $data['rejection_reason']
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'تعذر رفض الطلب';

            return $this->errorResponse($message, 422, $e->errors());
        }

        return $this->successResponse(
            new PaymentRequestResource($result),
            'تم رفض طلب الدفع'
        );
    }

    public function attachment(int $id, Request $request): StreamedResponse|JsonResponse
    {
        $paymentRequest = PaymentRequest::findOrFail($id);
        $actor = $request->user();

        if (! $this->paymentRequestService->authorizeAttachmentAccess($paymentRequest, $actor)) {
            return $this->forbiddenResponse('غير مصرح بعرض هذا المرفق');
        }

        return $this->paymentRequestService->streamAttachment($paymentRequest);
    }
}
