<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlanStoreRequest;
use App\Http\Requests\PlanUpdateRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $plans = Plan::query()->active()->orderBy('price_cents')->get();
        return $this->successResponse($plans, 'Plans retrieved successfully');
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $plans = Plan::query()->orderBy('price_cents')->get();
        return $this->successResponse($plans, 'All plans retrieved successfully');
    }

    public function show(Plan $plan): JsonResponse
    {
        return $this->successResponse($plan, 'Plan retrieved successfully');
    }

    public function store(PlanStoreRequest $request): JsonResponse
    {
        $plan = Plan::create($request->validated());
        return $this->createdResponse($plan, 'Plan created successfully');
    }

    public function update(PlanUpdateRequest $request, Plan $plan): JsonResponse
    {
        $plan->update($request->validated());
        return $this->successResponse($plan->fresh(), 'Plan updated successfully');
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $plan->delete();
        return $this->deletedResponse('Plan deleted successfully');
    }
}
