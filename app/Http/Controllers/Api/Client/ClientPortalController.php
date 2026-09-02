<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientInstallmentResource;
use App\Http\Traits\ApiResponse;
use App\Models\ClientAccount;
use App\Models\Installment;
use App\Models\InstallmentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientPortalController extends Controller
{
    use ApiResponse;

    public function dashboard(Request $request): JsonResponse
    {
        /** @var ClientAccount $client */
        $client = $request->user();
        $customerIds = $client->customers()->pluck('id');

        if ($customerIds->isEmpty()) {
            return $this->successResponse([
                'linked_customers' => 0,
                'total_plans' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'overdue_count' => 0,
                'pending_count' => 0,
                'next_due' => null,
                'installments' => [],
            ], 'لا توجد أقساط مرتبطة بحسابك بعد');
        }

        $installments = Installment::query()
            ->whereIn('customer_id', $customerIds)
            ->with(['user:id,name,email,phone', 'customer:id,name,email,phone', 'items.paymentRequests' => function ($q) {
                $q->where('status', 'pending');
            }])
            ->latest('id')
            ->get();

        $allItems = $installments->flatMap->items;
        $paidAmount = (float) $allItems->where('status', 'paid')->sum('paid_amount');
        $totalAmount = (float) $installments->sum('total_amount');
        $overdueCount = $allItems
            ->where('status', '!=', 'paid')
            ->filter(fn ($item) => $item->due_date < now()->startOfDay())
            ->count();
        $pendingCount = $allItems->where('status', '!=', 'paid')->count();

        $nextDue = $allItems
            ->where('status', '!=', 'paid')
            ->sortBy('due_date')
            ->first();

        return $this->successResponse([
            'linked_customers' => $customerIds->count(),
            'total_plans' => $installments->count(),
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(0, $totalAmount - $paidAmount),
            'overdue_count' => $overdueCount,
            'pending_count' => $pendingCount,
            'next_due' => $nextDue ? [
                'id' => $nextDue->id,
                'installment_id' => $nextDue->installment_id,
                'due_date' => $nextDue->due_date instanceof \DateTimeInterface
                    ? $nextDue->due_date->format('Y-m-d')
                    : $nextDue->due_date,
                'amount' => (float) $nextDue->amount,
                'status' => $nextDue->status,
            ] : null,
            'installments' => ClientInstallmentResource::collection($installments),
        ], 'تم جلب لوحة العميل بنجاح');
    }

    public function installmentList(Request $request): JsonResponse
    {
        /** @var ClientAccount $client */
        $client = $request->user();
        $customerIds = $client->customers()->pluck('id');

        $installments = Installment::query()
            ->whereIn('customer_id', $customerIds)
            ->with(['user:id,name,email,phone', 'customer:id,name,email,phone', 'items.paymentRequests' => function ($q) {
                $q->where('status', 'pending');
            }])
            ->latest('id')
            ->get();

        return $this->successResponse(
            ClientInstallmentResource::collection($installments),
            'تم جلب الأقساط بنجاح'
        );
    }

    public function installmentShow(int $id, Request $request): JsonResponse
    {
        /** @var ClientAccount $client */
        $client = $request->user();
        $customerIds = $client->customers()->pluck('id');

        $installment = Installment::query()
            ->whereIn('customer_id', $customerIds)
            ->with(['user:id,name,email,phone', 'customer:id,name,email,phone', 'items.paymentRequests' => function ($q) {
                $q->where('status', 'pending');
            }])
            ->find($id);

        if (! $installment) {
            return $this->notFoundResponse('القسط غير موجود');
        }

        return $this->successResponse(
            new ClientInstallmentResource($installment),
            'تم جلب القسط بنجاح'
        );
    }
}
