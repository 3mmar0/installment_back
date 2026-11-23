<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminClientAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_normalized' => $this->phone_normalized,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'is_verified' => $this->email_verified_at !== null,
            'last_active_at' => $this->last_active_at?->toISOString(),
            'customers_count' => (int) ($this->customers_count ?? 0),
            'personal_installments_count' => (int) ($this->personal_installments_count ?? 0),
            'customers' => $this->when(
                $this->relationLoaded('customers'),
                fn () => $this->customers->map(fn ($customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'merchant' => $customer->relationLoaded('user') && $customer->user
                        ? [
                            'id' => $customer->user->id,
                            'name' => $customer->user->name,
                            'email' => $customer->user->email,
                        ]
                        : null,
                    'created_at' => $customer->created_at?->toISOString(),
                ])->values()
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
