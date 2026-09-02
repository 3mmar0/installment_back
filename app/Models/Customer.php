<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_account_id',
        'name',
        'email',
        'phone',
        'phone_normalized',
        'address',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class);
    }

    public function installments()
    {
        return $this->hasMany(Installment::class);
    }
}
