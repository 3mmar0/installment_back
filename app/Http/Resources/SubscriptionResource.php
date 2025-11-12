<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
            'slug' => $this->slug,
            'currency' => $this->currency,
            'price' => $this->price,
            'duration' => $this->duration,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'customers' => $this->customers,
            'installments' => $this->installments,
            'notifications' => $this->notifications,
            'reports' => $this->reports,
            'features' => $this->features,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
