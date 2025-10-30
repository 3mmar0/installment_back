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
        $latestSubscription = $this->whenLoaded('latestSubscription') ?? $this->latestSubscription;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'current_subscription' => $latestSubscription ? [
                'id' => $latestSubscription->id,
                'status' => $latestSubscription->status,
                'starts_at' => $latestSubscription->starts_at?->toISOString(),
                'ends_at' => $latestSubscription->ends_at?->toISOString(),
                'next_due_at' => $latestSubscription->next_due_at?->toISOString(),
                'amount_cents' => $latestSubscription->amount_cents,
                'paid_cents' => $latestSubscription->paid_cents,
                'plan' => $latestSubscription->relationLoaded('plan') ? [
                    'id' => $latestSubscription->plan->id,
                    'name' => $latestSubscription->plan->name,
                    'interval' => $latestSubscription->plan->interval,
                ] : null,
            ] : null,
        ];
    }
}
