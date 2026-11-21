<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'client_account_id',
        'name',
        'total_amount',
        'invoice_price',
        'down_payment',
        'products',
        'start_date',
        'months',
        'end_date',
        'status',
        'notes',
        'attachment_path',
    ];

    protected $casts = [
        'products' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'invoice_price' => 'decimal:2',
        'down_payment' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class);
    }

    public function isPersonal(): bool
    {
        return $this->client_account_id !== null && $this->user_id === null;
    }

    public function items()
    {
        return $this->hasMany(InstallmentItem::class);
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->canManageMerchantData()) {
            return $query;
        }

        return $query->where(function ($builder) use ($user) {
            $builder->where('installments.user_id', $user->id)
                ->orWhereHas('customer', function ($customerQuery) use ($user) {
                    $customerQuery->where('customers.user_id', $user->id);
                });
        });
    }
}
