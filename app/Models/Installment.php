<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Installment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'total_amount',
        'products',
        'start_date',
        'months',
        'end_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'products' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function items()
    {
        return $this->hasMany(InstallmentItem::class);
    }

    public function scopeForUser($query, User $user)
    {
        return $user->isOwner() ? $query : $query->where('user_id', $user->id);
    }
}
