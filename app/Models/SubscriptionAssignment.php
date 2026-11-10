<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'start_date',
        'end_date',
        'status',
        'features',
        'source',
        'snapshot',
        'activated_by',
        'ended_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'features' => 'array',
        'snapshot' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function userLimit(): HasOne
    {
        return $this->hasOne(UserLimit::class, 'subscription_assignment_id');
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(SubscriptionPurchaseRequest::class, 'approved_assignment_id');
    }
}
