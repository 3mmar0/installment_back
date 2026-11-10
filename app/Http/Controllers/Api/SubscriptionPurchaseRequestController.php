<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserLimitResource;
use App\Http\Traits\ApiResponse;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPurchaseRequest;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionPurchaseRequestController extends Controller
{
    use ApiResponse;

    public function store(Request $request, SubscriptionEntitlementService $entitlements): JsonResponse
    {
        $data = $request->validate([
            'subscription_id' => ['required', 'integer', 'exists:subscriptions,id'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);
        $subscription = Subscription::active()->findOrFail($data['subscription_id']);
        if ((float) $subscription->price <= 0) return $this->errorResponse('اختر الباقة المجانية من المسار المخصص.', 422);

        $purchase = DB::transaction(function () use ($request, $data, $subscription, $entitlements) {
            $existing = SubscriptionPurchaseRequest::query()->where('user_id', $request->user()->id)->pending()->lockForUpdate()->first();
            if ($existing) return $existing;
            $purchase = SubscriptionPurchaseRequest::create([
                'user_id' => $request->user()->id, 'subscription_id' => $subscription->id,
                'plan_snapshot' => $entitlements->snapshot($subscription), 'status' => 'pending_review',
                'payment_reference' => $data['payment_reference'] ?? null, 'requested_at' => now(),
            ]);
            SubscriptionEvent::create(['user_id' => $request->user()->id, 'purchase_request_id' => $purchase->id, 'type' => 'purchase_requested', 'actor_id' => $request->user()->id, 'payload' => ['subscription_id' => $subscription->id]]);
            return $purchase;
        });

        return $this->successResponse($purchase, $purchase->wasRecentlyCreated ? 'تم إرسال طلب الاشتراك للمراجعة' : 'لديك طلب اشتراك قيد المراجعة');
    }

    public function current(Request $request): JsonResponse
    {
        return $this->successResponse(SubscriptionPurchaseRequest::where('user_id', $request->user()->id)->latest('id')->first(), 'تم جلب حالة طلب الاشتراك');
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $query = SubscriptionPurchaseRequest::with(['user', 'subscription', 'reviewer'])->latest('id');
        if ($status) $query->where('status', $status);
        return $this->successResponse($query->paginate(min((int) $request->query('per_page', 20), 100)), 'تم جلب طلبات الاشتراك');
    }

    public function approve(Request $request, SubscriptionPurchaseRequest $purchaseRequest, SubscriptionEntitlementService $entitlements): JsonResponse
    {
        $assignment = DB::transaction(function () use ($request, $purchaseRequest, $entitlements) {
            $purchase = SubscriptionPurchaseRequest::lockForUpdate()->findOrFail($purchaseRequest->id);
            if ($purchase->status === 'approved' && $purchase->approved_assignment_id) return $purchase->approvedAssignment;
            if (! in_array($purchase->status, SubscriptionPurchaseRequest::PENDING_STATUSES, true)) abort(422, 'لا يمكن اعتماد هذا الطلب بحالته الحالية.');
            $assignment = $entitlements->activate($purchase->subscription, $purchase->user_id, 'approved_purchase', $request->user()->id, $purchase);
            $purchase->update(['status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id, 'approved_assignment_id' => $assignment->id]);
            SubscriptionEvent::create(['assignment_id' => $assignment->id, 'user_id' => $purchase->user_id, 'purchase_request_id' => $purchase->id, 'type' => 'purchase_approved', 'actor_id' => $request->user()->id]);
            return $assignment;
        });
        return $this->successResponse(new UserLimitResource($assignment->userLimit()->first()), 'تم اعتماد وتفعيل الاشتراك');
    }

    public function reject(Request $request, SubscriptionPurchaseRequest $purchaseRequest): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        if (! in_array($purchaseRequest->status, SubscriptionPurchaseRequest::PENDING_STATUSES, true)) return $this->errorResponse('لا يمكن رفض هذا الطلب بحالته الحالية.', 422);
        $purchaseRequest->update(['status' => 'rejected', 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id, 'review_note' => $data['reason']]);
        SubscriptionEvent::create(['user_id' => $purchaseRequest->user_id, 'purchase_request_id' => $purchaseRequest->id, 'type' => 'purchase_rejected', 'actor_id' => $request->user()->id, 'payload' => ['reason' => $data['reason']]]);
        return $this->successResponse($purchaseRequest->fresh(), 'تم رفض طلب الاشتراك');
    }
}
