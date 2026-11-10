<?php

namespace App\Http\Controllers\Api;

use App\Helpers\TrialHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserLimitResource;
use App\Http\Traits\ApiResponse;
use App\Models\Subscription;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionTrialController extends Controller
{
    use ApiResponse;

    public function start(Request $request, SubscriptionEntitlementService $entitlements): JsonResponse
    {
        $data = $request->validate(['subscription_id' => ['required', 'integer', 'exists:subscriptions,id']]);
        $settings = TrialHelper::settings();
        if (! $settings['enabled']) return $this->errorResponse('التجربة المجانية غير متاحة حالياً.', 422);
        $user = $request->user();
        if ($user->trial_used_at) return $this->errorResponse('تم استخدام التجربة المجانية مسبقاً.', 422);
        $subscription = Subscription::active()->findOrFail($data['subscription_id']);
        if ((float) $subscription->price <= 0) return $this->errorResponse('التجربة متاحة للباقات المدفوعة فقط.', 422);

        $assignment = DB::transaction(function () use ($user, $subscription, $settings, $entitlements) {
            $locked = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            if ($locked->trial_used_at) abort(422, 'تم استخدام التجربة المجانية مسبقاً.');
            $locked->update(['trial_used_at' => now()]);
            return $entitlements->activate($subscription, $locked->id, 'trial', $locked->id, null, now()->addDays($settings['days'])->endOfDay(), ['is_trial' => true]);
        });
        return $this->successResponse(new UserLimitResource($assignment->userLimit()->first()), 'تم تفعيل التجربة المجانية');
    }
}
