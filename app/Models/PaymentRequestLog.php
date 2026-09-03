<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRequestLog extends Model
{
    public const ACTION_SUBMITTED = 'submitted';

    public const ACTION_RESUBMITTED = 'resubmitted';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_APPROVED = 'approved';

    protected $fillable = [
        'payment_request_id',
        'action',
        'paid_on',
        'note',
        'attachment_path',
        'attachment_mime',
        'attachment_size',
        'rejection_reason',
        'actor_user_id',
        'actor_client_id',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'attachment_size' => 'integer',
        ];
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorClient(): BelongsTo
    {
        return $this->belongsTo(ClientAccount::class, 'actor_client_id');
    }
}
