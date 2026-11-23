<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminClientAccountResource;
use App\Http\Traits\ApiResponse;
use App\Services\AdminClientAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminClientAccountController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AdminClientAccountService $adminClientAccountService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'verified' => ['sometimes', 'nullable', 'string', 'in:yes,no'],
            'linked' => ['sometimes', 'nullable', 'string', 'in:yes,no'],
        ]);

        $accounts = $this->adminClientAccountService->list($validated);

        return $this->successResponse(
            AdminClientAccountResource::collection($accounts)->response()->getData(true),
            'تم جلب حسابات تطبيق العملاء بنجاح'
        );
    }

    public function show(int $id): JsonResponse
    {
        $account = $this->adminClientAccountService->find($id);

        return $this->successResponse(
            new AdminClientAccountResource($account),
            'تم جلب حساب تطبيق العميل بنجاح'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->adminClientAccountService->delete($id);

        return $this->deletedResponse('تم حذف حساب تطبيق العميل بنجاح');
    }
}
