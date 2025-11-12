<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\InstallmentServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInstallmentRequest;
use App\Http\Resources\InstallmentItemResource;
use App\Http\Resources\InstallmentResource;
use App\Http\Traits\ApiResponse;
use App\Models\InstallmentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstallmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly InstallmentServiceInterface $installmentService
    ) {}

    /**
     * Get all installments for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $installments = $this->installmentService->getInstallmentsForUser($request->user());

        return $this->successResponse(
            InstallmentResource::collection($installments)->response()->getData(true),
            'تم جلب الأقساط بنجاح'
        );
    }

    /**
     * Create a new installment.
     */
    public function store(StoreInstallmentRequest $request): JsonResponse
    {
        /** @var \App\Models\User $authUser */
        // @phpstan-ignore-next-line
        $authUser = auth()->user();

        $installment = $this->installmentService->createInstallment(
            $request->validated(),
            $authUser
        );

        return $this->createdResponse(
            new InstallmentResource($installment),
            'تم إنشاء القسط بنجاح'
        );
    }

    /**
     * Get a specific installment.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $installment = $this->installmentService->findInstallmentById($id);

        if (!$installment) {
            return $this->notFoundResponse('القسط غير موجود');
        }

        // Check authorization
        if (!$request->user()->isOwner() && $installment->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('غير مصرح لك بعرض هذا القسط');
        }

        return $this->successResponse(
            new InstallmentResource($installment),
            'تم جلب القسط بنجاح'
        );
    }

    /**
     * Mark an installment item as paid.
     */
    public function markItemPaid(InstallmentItem $item, Request $request): JsonResponse
    {
        $data = $request->validate([
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $updatedItem = $this->installmentService->markItemPaid($item, $data, $request->user());

            return $this->successResponse(
                new InstallmentItemResource($updatedItem),
                'تم تسجيل الدفعة بنجاح'
            );
        } catch (\Exception $e) {
            if ($e->getCode() === 403) {
                return $this->forbiddenResponse($e->getMessage());
            }
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get dashboard analytics.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $analytics = $this->installmentService->getDashboardAnalytics($request->user());

        return $this->successResponse($analytics, 'تم جلب تحليلات لوحة التحكم بنجاح');
    }

    /**
     * Get installment statistics.
     */
    public function stats(int $id, Request $request): JsonResponse
    {
        $stats = $this->installmentService->getInstallmentStats($id, $request->user());

        if (empty($stats)) {
            return $this->notFoundResponse('القسط غير موجود أو غير مصرح به');
        }

        return $this->successResponse($stats, 'تم جلب إحصائيات القسط بنجاح');
    }

    /**
     * Get all installments statistics summary.
     */
    public function allStats(Request $request): JsonResponse
    {
        $stats = $this->installmentService->getAllInstallmentsStats($request->user());

        return $this->successResponse($stats, 'تم جلب إحصائيات جميع الأقساط بنجاح');
    }

    /**
     * Get overdue installment items.
     */
    public function overdue(Request $request): JsonResponse
    {
        $items = $this->installmentService->getOverdueItems($request->user());

        return $this->successResponse(
            InstallmentItemResource::collection($items),
            'تم جلب الأقساط المتأخرة بنجاح'
        );
    }

    /**
     * Get due soon installment items.
     */
    public function dueSoon(Request $request): JsonResponse
    {
        $items = $this->installmentService->getDueSoonItems($request->user());

        return $this->successResponse(
            InstallmentItemResource::collection($items),
            'تم جلب الأقساط المستحقة قريباً بنجاح'
        );
    }
}
