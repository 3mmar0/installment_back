<?php

namespace App\Http\Resources;

use App\Helpers\TrialHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLimitResource extends JsonResource
{
    /**
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
            'is_trial' => TrialHelper::isTrialFeatures($this->features),
            'limits' => [
                'customers' => $this->customers,
                'installments' => $this->installments,
                'notifications' => $this->notifications,
                'assistant_messages' => $this->assistant_messages,
                'whatsapp_messages' => $this->whatsapp_messages,
                'features' => $this->features,
                'reports' => $this->reports,
            ],
            'usage' => [
                'customers_used' => $this->customers_used,
                'installments_used' => $this->installments_used,
                'notifications_used' => $this->notifications_used,
                'assistant_messages_used' => $this->assistant_messages_used,
                'whatsapp_messages_used' => $this->whatsapp_messages_used,
                'assistant_messages_bonus' => $this->assistant_messages_bonus,
                'whatsapp_messages_bonus' => $this->whatsapp_messages_bonus,
                'usage_period' => $this->usage_period,
            ],
            'remaining' => [
                'customers' => $this->getRemainingCount('customers'),
                'installments' => $this->getRemainingCount('installments'),
                'notifications' => $this->getRemainingCount('notifications'),
                'assistant_messages' => $this->getRemainingCount('assistant_messages'),
                'whatsapp_messages' => $this->getRemainingCount('whatsapp_messages'),
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
