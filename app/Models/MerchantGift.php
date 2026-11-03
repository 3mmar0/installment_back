<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantGift extends Model
{
    public const RESOURCES = [
        'assistant_messages',
        'whatsapp_messages',
    ];

    protected $fillable = [
        'user_id',
        'granted_by',
        'resource',
        'amount',
        'source',
        'note',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
