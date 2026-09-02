<?php

namespace App\Http\Resources;

use App\Enums\PaymentRequestStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientInstallmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items')
            ? $this->items->map(fn ($item) => $this->transformItem($item))
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'total_amount' => (float) $this->total_amount,
            'products' => $this->products ?? [],
            'start_date' => $this->start_date instanceof \DateTimeInterface
                ? $this->start_date->format('Y-m-d')
                : $this->start_date,
            'end_date' => $this->end_date instanceof \DateTimeInterface
                ? $this->end_date->format('Y-m-d')
                : $this->end_date,
            'months' => $this->months,
            'status' => $this->status,
            'vendor' => $this->when(
                $this->relationLoaded('user'),
                fn () => [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'phone' => $this->user?->phone,
                    'email' => $this->user?->email,
                ]
            ),
            'customer' => $this->when(
                $this->relationLoaded('customer'),
                fn () => [
                    'id' => $this->customer?->id,
                    'name' => $this->customer?->name,
                    'email' => $this->customer?->email,
                    'phone' => $this->customer?->phone,
                ]
            ),
            'items' => $items,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function transformItem($item): array
    {
        $dueDate = $item->due_date instanceof \DateTimeInterface
            ? $item->due_date->format('Y-m-d')
            : $item->due_date;

        $pendingRequest = null;
        if ($item->relationLoaded('paymentRequests')) {
            $pending = $item->paymentRequests
                ->first(fn ($pr) => $pr->status === PaymentRequestStatus::Pending
                    || $pr->status === PaymentRequestStatus::Pending->value);

            if ($pending) {
                $pendingRequest = [
                    'id' => $pending->id,
                    'status' => $pending->status instanceof PaymentRequestStatus
                        ? $pending->status->value
                        : $pending->status,
                    'paid_on' => $pending->paid_on instanceof \DateTimeInterface
                        ? $pending->paid_on->format('Y-m-d')
                        : $pending->paid_on,
                    'amount' => (float) $pending->amount,
                ];
            }
        }

        return [
            'id' => $item->id,
            'installment_id' => $item->installment_id,
            'due_date' => $dueDate,
            'amount' => (float) $item->amount,
            'paid_amount' => $item->paid_amount !== null ? (float) $item->paid_amount : null,
            'status' => $item->status,
            'paid_at' => $item->paid_at?->toISOString(),
            'reference' => $item->reference,
            'pending_payment_request' => $pendingRequest,
        ];
    }
}
