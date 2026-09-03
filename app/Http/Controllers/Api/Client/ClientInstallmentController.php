<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientInstallmentRequest;
use App\Http\Resources\ClientInstallmentResource;
use App\Http\Traits\ApiResponse;
use App\Models\ClientAccount;
use App\Models\InstallmentItem;
use App\Services\ClientInstallmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientInstallmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ClientInstallmentService $clientInstallmentService
    ) {}

    public function store(StoreClientInstallmentRequest $request): JsonResponse
    {
        /** @var ClientAccount $client */
        $client = $request->user();

        $installment = $this->clientInstallmentService->createPersonal(
            $client,
            $request->validated()
        );

        return $this->createdResponse(
            new ClientInstallmentResource($installment),
            'تم إنشاء القسط بنجاح'
        );
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var ClientAccount $client */
        $client = $request->user();

        $installment = $this->clientInstallmentService->updatePersonal($client, $id, $data);

        return $this->successResponse(
            new ClientInstallmentResource($installment),
            'تم تحديث القسط بنجاح'
        );
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        /** @var ClientAccount $client */
        $client = $request->user();

        $this->clientInstallmentService->deletePersonal($client, $id);

        return $this->successResponse(null, 'تم حذف القسط بنجاح');
    }

    public function markItemPaid(int $item, Request $request): JsonResponse
    {
        $data = $request->validate([
            'paid_amount' => ['nullable', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var ClientAccount $client */
        $client = $request->user();
        $installmentItem = InstallmentItem::with('installment')->findOrFail($item);

        $updated = $this->clientInstallmentService->markItemPaid(
            $client,
            $installmentItem,
            $data
        );

        return $this->successResponse(
            [
                'id' => $updated->id,
                'status' => $updated->status,
                'paid_amount' => (float) $updated->paid_amount,
                'paid_at' => $updated->paid_at?->toISOString(),
                'reference' => $updated->reference,
            ],
            'تم تسجيل الدفع بنجاح'
        );
    }
}
