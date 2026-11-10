<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPurchaseRequest extends Model
{
    use HasFactory;

    public const PENDING_STATUSES = ['pending_payment', 'pending_review'];

    protected $fillable = [
        'user_id', 'subscription_id', 'plan_snapshot', 'status', 'payment_reference',
        'requested_at', 'reviewed_at', 'reviewed_by', 'review_note', 'approved_assignment_id',
    ];

    protected $casts = [
        'plan_snapshot' => 'array',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function approvedAssignment(): BelongsTo { return $this->belongsTo(SubscriptionAssignment::class, 'approved_assignment_id'); }

    public function scopePending($query)
    {
        return $query->whereIn('status', self::PENDING_STATUSES);
    }
}
