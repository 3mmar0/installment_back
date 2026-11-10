<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppDailyCharge extends Model
{
    protected $table = 'whatsapp_daily_charges';

    protected $fillable = [
        'user_id',
        'customer_id',
        'charge_date',
        'messages_sent',
    ];

    protected $casts = [
        'charge_date' => 'date',
        'messages_sent' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
