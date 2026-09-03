<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentRequestLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'paid_on' => $this->paid_on instanceof \DateTimeInterface
                ? $this->paid_on->format('Y-m-d')
                : $this->paid_on,
            'note' => $this->note,
            'attachment_mime' => $this->attachment_mime,
            'attachment_size' => $this->attachment_size,
            'has_attachment' => ! empty($this->attachment_path),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
