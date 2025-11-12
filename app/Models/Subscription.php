<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'currency',
        'price',
        'duration',
        'description',
        'customers',
        'installments',
        'notifications',
        'reports',
        'features',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'customers' => 'array',
        'installments' => 'array',
        'notifications' => 'array',
        'features' => 'array',
        'reports' => 'boolean',
        'is_active' => 'boolean',
        'price' => 'float',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SubscriptionAssignment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
