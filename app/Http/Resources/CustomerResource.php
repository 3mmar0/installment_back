<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'phone' => $this->phone,
            'national_id' => $this->national_id,
            'address' => $this->address,
            'notes' => $this->notes,
            'guarantor_name' => $this->guarantor_name,
            'guarantor_national_id' => $this->guarantor_national_id,
            'guarantor_phone' => $this->guarantor_phone,
            'has_client_account' => $this->client_account_id !== null,
            'client_account' => $this->when(
                $this->relationLoaded('clientAccount') && $this->clientAccount,
                fn () => [
                    'id' => $this->clientAccount->id,
                    'name' => $this->clientAccount->name,
                    'email' => $this->clientAccount->email,
                    'phone' => $this->clientAccount->phone,
                ]
            ),
            'user' => new UserResource($this->whenLoaded('user')),
            'installments_count' => $this->whenCounted('installments'),
            'installments' => InstallmentResource::collection($this->whenLoaded('installments')),
            'client_account_installments' => InstallmentResource::collection(
                $this->whenLoaded('clientAccountInstallments')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
