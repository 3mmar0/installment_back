<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\CustomerServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Traits\ApiResponse;
use App\Services\EmailNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CustomerServiceInterface $customerService,
        private readonly EmailNotificationService $emailNotificationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $customers = $this->customerService->getCustomersForUser(
            $request->user(),
            $validated
        );

        return $this->successResponse(
            CustomerResource::collection($customers)->response()->getData(true),
            'تم جلب العملاء بنجاح'
        );
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        /** @var \App\Models\User $authUser */
        // @phpstan-ignore-next-line
        $authUser = auth()->user();

        $customer = $this->customerService->createCustomer(
            $request->validated(),
            $authUser
        );

        return $this->createdResponse(
            new CustomerResource($customer),
            'تم إنشاء العميل بنجاح'
        );
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $customer = $this->customerService->findCustomerById($id);

        if (!$customer) {
            return $this->notFoundResponse('العميل غير موجود');
        }

        $this->authorize('view', $customer);

        $customer->load(['installments.items', 'user']);

        return $this->successResponse(
            new CustomerResource($customer),
            'تم جلب العميل بنجاح'
        );
    }

    public function update(int $id, UpdateCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->findCustomerById($id);

        if (!$customer) {
            return $this->notFoundResponse('العميل غير موجود');
        }

        $this->authorize('update', $customer);

        $customer = $this->customerService->updateCustomer(
            $id,
            $request->validated(),
            $request->user()
        );

        return $this->successResponse(
            new CustomerResource($customer),
            'تم تحديث العميل بنجاح'
        );
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $customer = $this->customerService->findCustomerById($id);

        if (!$customer) {
            return $this->notFoundResponse('العميل غير موجود');
        }

        $this->authorize('delete', $customer);

        $this->customerService->deleteCustomer($id, $request->user());

        return $this->deletedResponse('تم حذف العميل بنجاح');
    }

    public function stats(int $id, Request $request): JsonResponse
    {
        $customer = $this->customerService->findCustomerById($id);

        if (!$customer) {
            return $this->notFoundResponse('العميل غير موجود');
        }

        $this->authorize('view', $customer);

        $stats = $this->customerService->getCustomerStats($customer);

        return $this->successResponse($stats, 'تم جلب إحصائيات العميل بنجاح');
    }

    public function sendReminders(int $id, Request $request): JsonResponse
    {
        $customer = $this->customerService->findCustomerById($id);

        if (!$customer) {
            return $this->notFoundResponse('العميل غير موجود');
        }

        $this->authorize('view', $customer);

        $result = $this->emailNotificationService->queueCustomerPaymentReminders(
            $customer,
            $request->user()
        );

        if (($result['items_included'] ?? 0) === 0) {
            return $this->successResponse(
                $result,
                'لا توجد دفعات مستحقة أو متأخرة لإرسال تذكير لها'
            );
        }

        return $this->successResponse(
            $result,
            "تمت جدولة {$result['total_emails']} بريد إلكتروني للعميل"
        );
    }

    public function forSelect(Request $request): JsonResponse
    {
        $customers = $this->customerService->getCustomersForUser($request->user());

        $selectData = $customers->getCollection()->map(function ($customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'label' => "{$customer->name} ({$customer->email})",
            ];
        });

        return $this->successResponse([
            'data' => $selectData->values(),
        ], 'تم جلب العملاء بنجاح');
    }
}
