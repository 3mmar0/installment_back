<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A frozen, versioned copy of the scoring configuration. Once published its
 * `configuration` never changes, guaranteeing every historical snapshot that
 * references it stays reproducible.
 */
class CreditScoreModelVersion extends Model
{
    protected $fillable = [
        'version',
        'label',
        'description',
        'configuration',
        'is_active',
        'effective_at',
    ];

    protected $casts = [
        'configuration' => 'array',
        'is_active' => 'boolean',
        'effective_at' => 'datetime',
    ];

    public function scores(): HasMany
    {
        return $this->hasMany(CustomerCreditScore::class, 'model_version_id');
    }
}
