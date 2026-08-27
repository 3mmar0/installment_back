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
        $dueDate = $this->due_date instanceof \DateTimeInterface
            ? $this->due_date->format('Y-m-d')
            : $this->due_date;

        return [
            'id' => $this->id,
            'installment_id' => $this->installment_id,
            'due_date' => $dueDate,
            'amount' => (float) $this->amount,
            'paid_amount' => $this->paid_amount ? (float) $this->paid_amount : null,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toISOString(),
            'reference' => $this->reference,
            'payment_reference' => $this->reference,
            'note' => $this->note,
            'customer_id' => $this->when(
                $this->relationLoaded('installment'),
                fn () => $this->installment?->customer_id
            ),
            'customer_name' => $this->when(
                $this->relationLoaded('installment'),
                fn () => $this->installment?->customer?->name
            ),
            'customer_phone' => $this->when(
                $this->relationLoaded('installment'),
                fn () => $this->installment?->customer?->phone
            ),
            'customer_email' => $this->when(
                $this->relationLoaded('installment'),
                fn () => $this->installment?->customer?->email
            ),
            'days_until_due' => InstallmentDateHelper::daysUntilDue($dueDate),
            'days_overdue' => InstallmentDateHelper::daysOverdue($dueDate),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
