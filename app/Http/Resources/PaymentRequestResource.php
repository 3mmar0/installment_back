<?php

namespace App\Http\Resources;

use App\Enums\PaymentRequestStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof PaymentRequestStatus
            ? $this->status->value
            : $this->status;

        return [
            'id' => $this->id,
            'installment_item_id' => $this->installment_item_id,
            'installment_id' => $this->installment_id,
            'client_account_id' => $this->client_account_id,
            'user_id' => $this->user_id,
            'amount' => (float) $this->amount,
            'paid_on' => $this->paid_on instanceof \DateTimeInterface
                ? $this->paid_on->format('Y-m-d')
                : $this->paid_on,
            'note' => $this->note,
            'attachment_mime' => $this->attachment_mime,
            'attachment_size' => $this->attachment_size,
            'has_attachment' => ! empty($this->attachment_path),
            'status' => $status,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'client' => $this->when(
                $this->relationLoaded('clientAccount'),
                fn () => [
                    'id' => $this->clientAccount?->id,
                    'name' => $this->clientAccount?->name,
                    'email' => $this->clientAccount?->email,
                    'phone' => $this->clientAccount?->phone,
                ]
            ),
            'vendor' => $this->when(
                $this->relationLoaded('vendor'),
                fn () => [
                    'id' => $this->vendor?->id,
                    'name' => $this->vendor?->name,
                    'phone' => $this->vendor?->phone,
                    'email' => $this->vendor?->email,
                ]
            ),
            'installment' => $this->when(
                $this->relationLoaded('installment'),
                fn () => [
                    'id' => $this->installment?->id,
                    'name' => $this->installment?->name,
                    'total_amount' => $this->installment
                        ? (float) $this->installment->total_amount
                        : null,
                    'customer' => $this->installment?->relationLoaded('customer')
                        ? [
                            'id' => $this->installment->customer?->id,
                            'name' => $this->installment->customer?->name,
                            'email' => $this->installment->customer?->email,
                            'phone' => $this->installment->customer?->phone,
                        ]
                        : null,
                ]
            ),
            'installment_item' => $this->when(
                $this->relationLoaded('installmentItem'),
                fn () => [
                    'id' => $this->installmentItem?->id,
                    'due_date' => $this->installmentItem?->due_date instanceof \DateTimeInterface
                        ? $this->installmentItem->due_date->format('Y-m-d')
                        : $this->installmentItem?->due_date,
                    'amount' => $this->installmentItem
                        ? (float) $this->installmentItem->amount
                        : null,
                    'status' => $this->installmentItem?->status,
                ]
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
