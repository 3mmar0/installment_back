<?php

namespace App\Http\Controllers\Api;

use App\Helpers\TrialHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    use ApiResponse;

    public const ONBOARDING_INTRO_VIDEO_KEY = 'onboarding_intro_video_url';

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

    public function onboardingPublic(): JsonResponse
    {
        return $this->successResponse(
            $this->onboardingPayload(),
            'تم جلب إعدادات شاشة الترحيب'
        );
    }

    public function onboardingAdmin(): JsonResponse
    {
        return $this->successResponse(
            $this->onboardingPayload(),
            'تم جلب إعدادات شاشة الترحيب'
        );
    }

    public function updateOnboarding(Request $request): JsonResponse
    {
        $data = $request->validate([
            'video' => ['nullable', 'file', 'mimes:mp4,webm,ogg,mov,m4v', 'max:51200'],
            'video_url' => ['nullable', 'string', 'max:2048'],
            'clear_video' => ['nullable', 'boolean'],
        ]);

        $clear = filter_var($data['clear_video'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($clear) {
            $this->forgetOnboardingVideo();

            return $this->successResponse(
                $this->onboardingPayload(),
                'تم حذف فيديو شاشة الترحيب'
            );
        }

        if ($request->hasFile('video')) {
            $this->forgetOnboardingVideoFile();
            $path = $request->file('video')->store('onboarding', 'public');
            AppSetting::set(self::ONBOARDING_INTRO_VIDEO_KEY, asset('storage/'.$path));
        } elseif (array_key_exists('video_url', $data)) {
            $url = trim((string) ($data['video_url'] ?? ''));
            if ($url === '') {
                $this->forgetOnboardingVideo();
            } else {
                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    return $this->errorResponse('رابط الفيديو غير صالح', 422);
                }
                $this->forgetOnboardingVideoFile();
                AppSetting::set(self::ONBOARDING_INTRO_VIDEO_KEY, $url);
            }
        }

        return $this->successResponse(
            $this->onboardingPayload(),
            'تم تحديث فيديو شاشة الترحيب'
        );
    }

    /**
     * @return array{intro_video_url: string|null}
     */
    private function onboardingPayload(): array
    {
        $url = AppSetting::get(self::ONBOARDING_INTRO_VIDEO_KEY);
        $intro = is_string($url) && trim($url) !== '' ? trim($url) : null;

        return ['intro_video_url' => $intro];
    }

    private function forgetOnboardingVideo(): void
    {
        $this->forgetOnboardingVideoFile();
        AppSetting::set(self::ONBOARDING_INTRO_VIDEO_KEY, '');
    }

    private function forgetOnboardingVideoFile(): void
    {
        $current = AppSetting::get(self::ONBOARDING_INTRO_VIDEO_KEY);
        if (! is_string($current) || $current === '') {
            return;
        }

        $marker = '/storage/onboarding/';
        $pos = strpos($current, $marker);
        if ($pos === false) {
            return;
        }

        $relative = 'onboarding/'.ltrim(substr($current, $pos + strlen($marker)), '/');
        if ($relative !== 'onboarding/' && Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }
}
