<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InstallmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'installment_id',
        'due_date',
        'amount',
        'paid_at',
        'paid_amount',
        'status',
        'reference',
        'note',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function installment()
    {
        return $this->belongsTo(Installment::class);
    }

    public function markPaid(float $amount, ?string $reference = null): void
    {
        $this->update([
            'paid_at' => now(),
            'paid_amount' => $amount,
            'status' => 'paid',
            'reference' => $reference,
        ]);
    }
}
