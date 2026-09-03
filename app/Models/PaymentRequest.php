<?php

namespace App\Models;

use App\Enums\PaymentRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentRequest extends Model
{
    protected $fillable = [
        'installment_item_id',
        'installment_id',
        'client_account_id',
        'user_id',
        'amount',
        'paid_on',
        'note',
        'attachment_path',
        'attachment_mime',
        'attachment_size',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'pending_item_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'reviewed_at' => 'datetime',
            'status' => PaymentRequestStatus::class,
            'attachment_size' => 'integer',
        ];
    }

    public function installmentItem(): BelongsTo
    {
        return $this->belongsTo(InstallmentItem::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    public function clientAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAccount::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentRequestLog::class)->latest('id');
    }

    public function isPending(): bool
    {
        return $this->status === PaymentRequestStatus::Pending;
    }
}
