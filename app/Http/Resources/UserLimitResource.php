<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLimitResource extends JsonResource
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
            'user_id' => $this->user_id,
            'subscription_name' => $this->subscription_name,
            'subscription_slug' => $this->subscription_slug,
            'currency' => $this->currency,
            'price' => $this->price,
            'duration' => $this->duration,
            'description' => $this->description,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'limits' => [
                'customers' => $this->customers,
                'installments' => $this->installments,
                'notifications' => $this->notifications,
                'features' => $this->features,
                'reports' => $this->reports,
            ],
            'usage' => [
                'customers_used' => $this->customers_used,
                'installments_used' => $this->installments_used,
                'notifications_used' => $this->notifications_used,
            ],
            'remaining' => [
                'customers' => $this->getRemainingCount('customers'),
                'installments' => $this->getRemainingCount('installments'),
                'notifications' => $this->getRemainingCount('notifications'),
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
