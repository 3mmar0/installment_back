<?php

namespace App\Http\Resources;

use App\Helpers\TrialHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $canSeeActivity = $viewer && ($viewer->isOwner() || $viewer->isPlatformAdmin());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'registration_source' => $this->registration_source?->value ?? 'web',
            'is_platform_admin' => $this->isPlatformAdmin(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'last_active_at' => $this->when(
                $canSeeActivity,
                fn () => $this->last_active_at?->toISOString(),
            ),
            'is_active' => $this->when(
                $canSeeActivity,
                fn () => $this->isRecentlyActive(),
            ),
            'user_limit' => $this->whenLoaded('userLimit', function () {
                return new UserLimitResource($this->userLimit);
            }),
            'current_subscription' => $this->whenLoaded('userLimit', function () {
                return [
                    'name' => $this->userLimit->subscription_name,
                    'slug' => $this->userLimit->subscription_slug,
                    'status' => $this->userLimit->status,
                    'start_date' => $this->userLimit->start_date?->toDateString(),
                    'end_date' => $this->userLimit->end_date?->toDateString(),
                    'currency' => $this->userLimit->currency,
                    'price' => $this->userLimit->price,
                    'duration' => $this->userLimit->duration,
                    'is_trial' => TrialHelper::isTrialFeatures($this->userLimit->features),
                ];
            }),
        ];
    }
}
