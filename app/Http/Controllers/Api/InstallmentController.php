<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\InstallmentServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\MarkItemPaidRequest;
use App\Http\Requests\StoreInstallmentRequest;
use App\Http\Requests\UpdateInstallmentRequest;
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
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'in:all,active,completed,cancelled,overdue'],
            'customer_id' => ['sometimes', 'integer', 'min:1'],
            'user_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $installments = $this->installmentService->getInstallmentsForUser(
            $request->user(),
            $validated
        );

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

        $this->authorize('view', $installment);

        return $this->successResponse(
            new InstallmentResource($installment),
            'تم جلب القسط بنجاح'
        );
    }

    public function update(int $id, UpdateInstallmentRequest $request): JsonResponse
    {
        $installment = $this->installmentService->findInstallmentById($id);

        if (!$installment) {
            return $this->notFoundResponse('القسط غير موجود');
        }

        $this->authorize('update', $installment);

        $installment = $this->installmentService->updateInstallment(
            $id,
            $request->validated(),
            $request->user()
        );

        return $this->successResponse(
            new InstallmentResource($installment),
            'تم تحديث القسط بنجاح'
        );
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $installment = $this->installmentService->findInstallmentById($id);

        if (!$installment) {
            return $this->notFoundResponse('القسط غير موجود');
        }

        $this->authorize('delete', $installment);

        $this->installmentService->deleteInstallment($id, $request->user());

        return $this->deletedResponse('تم حذف القسط بنجاح');
    }

    public function markItemPaid(InstallmentItem $item, MarkItemPaidRequest $request): JsonResponse
    {
        $this->authorize('update', $item);

        $updatedItem = $this->installmentService->markItemPaid(
            $item,
            $request->validated(),
            $request->user()
        );

        return $this->successResponse(
            new InstallmentItemResource($updatedItem),
            'تم تسجيل الدفعة بنجاح'
        );
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
        $installment = $this->installmentService->findInstallmentById($id);

        if (!$installment) {
            return $this->notFoundResponse('القسط غير موجود أو غير مصرح به');
        }

        $this->authorize('view', $installment);

        $stats = $this->installmentService->getInstallmentStats($id, $request->user());

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

    /**
     * Send due-date reminder notifications (and customer email when available).
     */
    public function sendReminders(int $id, Request $request): JsonResponse
    {
        $installment = $this->installmentService->findInstallmentById($id);

        if (!$installment) {
            return $this->notFoundResponse('القسط غير موجود');
        }

        $this->authorize('update', $installment);

        $validated = $request->validate([
            'item_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $result = $this->installmentService->sendInstallmentDueReminders(
            $id,
            $request->user(),
            isset($validated['item_id']) ? (int) $validated['item_id'] : null
        );

        if ($result['items_reminded'] === 0) {
            return $this->successResponse(
                $result,
                'لا توجد دفعات غير مدفوعة لإرسال تذكير لها'
            );
        }

        return $this->successResponse(
            $result,
            'تمت جدولة التذكيرات للإرسال'
        );
    }
}
