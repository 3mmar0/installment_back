<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use App\Services\Assistant\AssistantOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AssistantOrchestrator $orchestrator
    ) {}

    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse(
            $this->orchestrator->status($user),
            'حالة المساعد'
        );
    }

    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'session_id' => ['nullable', 'uuid'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $result = $this->orchestrator->handleMessage(
            $user,
            trim($validated['message']),
            $validated['session_id'] ?? null
        );

        return $this->successResponse($result, 'رد المساعد');
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pending_action_id' => ['required', 'uuid'],
            'confirmed' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $result = $this->orchestrator->confirmAction(
            $user,
            $validated['pending_action_id'],
            filter_var($validated['confirmed'], FILTER_VALIDATE_BOOLEAN)
        );

        return $this->successResponse($result, 'نتيجة التأكيد');
    }
}
