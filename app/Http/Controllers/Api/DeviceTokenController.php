<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\ClientAccount;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    use ApiResponse;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', 'in:android,ios'],
        ]);

        $owner = $request->user();
        $isClient = $owner instanceof ClientAccount;

        $token = DeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'platform' => $data['platform'],
                'user_id' => $isClient ? null : $owner->id,
                'client_account_id' => $isClient ? $owner->id : null,
                'last_used_at' => now(),
            ]
        );

        return $this->successResponse(
            ['id' => $token->id],
            'تم تسجيل رمز الجهاز بنجاح'
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        $owner = $request->user();
        $query = DeviceToken::query()->where('token', $data['token']);

        if ($owner instanceof ClientAccount) {
            $query->where('client_account_id', $owner->id);
        } elseif ($owner instanceof User) {
            $query->where('user_id', $owner->id);
        }

        $query->delete();

        return $this->successResponse(null, 'تم إلغاء تسجيل رمز الجهاز بنجاح');
    }
}
