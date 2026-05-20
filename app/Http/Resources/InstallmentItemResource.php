<?php

namespace App\Http\Resources;

use App\Helpers\InstallmentDateHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentItemResource extends JsonResource
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
            'installment_id' => $this->installment_id,
            'due_date' => $this->due_date,
            'amount' => (float) $this->amount,
            'paid_amount' => $this->paid_amount ? (float) $this->paid_amount : null,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toISOString(),
            'reference' => $this->reference,
            'payment_reference' => $this->reference,
            'customer_id' => $this->installment?->customer_id,
            'customer_name' => $this->installment?->customer?->name,
            'customer_phone' => $this->installment?->customer?->phone,
            'customer_email' => $this->installment?->customer?->email,
            'days_until_due' => InstallmentDateHelper::daysUntilDue($this->due_date),
            'days_overdue' => InstallmentDateHelper::daysOverdue($this->due_date),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
