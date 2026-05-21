<?php

namespace App\Http\Controllers\Api;

use App\Helpers\TrialHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    use ApiResponse;

    public function trialPublic(): JsonResponse
    {
        return $this->successResponse(TrialHelper::settings(), 'تم جلب إعدادات التجربة');
    }

    public function updateTrial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $settings = TrialHelper::updateSettings($data['enabled'], $data['days']);

        return $this->successResponse($settings, 'تم تحديث إعدادات التجربة المجانية');
    }
}
