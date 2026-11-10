<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionEvent extends Model
{
    use HasFactory;

    protected $fillable = ['assignment_id', 'user_id', 'purchase_request_id', 'type', 'actor_id', 'payload'];

    protected $casts = ['payload' => 'array'];
}
