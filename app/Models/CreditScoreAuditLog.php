<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditScoreAuditLog extends Model
{
    public const ACTION_MANUAL_RECALCULATION = 'manual_recalculation';
    public const ACTION_VERSION_PUBLISHED = 'version_published';
    public const ACTION_CONFIGURATION_CHANGED = 'configuration_changed';

    protected $fillable = [
        'actor_user_id',
        'customer_id',
        'action',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
