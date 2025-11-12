<?php

namespace App\Http\Resources;

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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
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
                ];
            }),
        ];
    }
}
