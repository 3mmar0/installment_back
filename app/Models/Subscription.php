<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'canceled_at',
        'next_due_at',
        'amount_cents',
        'paid_cents',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'next_due_at' => 'datetime',
        'amount_cents' => 'integer',
        'paid_cents' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SubscriptionTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isPastDue(): bool
    {
        return $this->paid_cents < $this->amount_cents && optional($this->next_due_at)->isPast();
    }

    public function isExpired(): bool
    {
        return optional($this->ends_at)->isPast() && $this->status !== 'canceled';
    }

    public function refreshComputedStatus(): void
    {
        if ($this->status === 'canceled') {
            return;
        }
        if ($this->isExpired()) {
            $this->status = 'expired';
        } elseif ($this->isPastDue()) {
            $this->status = 'past_due';
        } else {
            $this->status = 'active';
        }
    }
}
