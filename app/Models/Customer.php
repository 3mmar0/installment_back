<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'national_id',
        'address',
        'job',
        'monthly_salary',
        'notes',
        'guarantor_name',
        'guarantor_national_id',
        'guarantor_phone',
        'current_credit_score_id',
        'credit_score_dirty_at',
    ];

    protected $casts = [
        'credit_score_dirty_at' => 'datetime',
        'monthly_salary' => 'decimal:2',
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

    /**
     * Every Internal Credit Score snapshot ever produced for this customer.
     */
    public function creditScores(): HasMany
    {
        return $this->hasMany(CustomerCreditScore::class);
    }

    /**
     * Fast pointer to the latest snapshot (list & dashboard reads).
     */
    public function currentCreditScore(): BelongsTo
    {
        return $this->belongsTo(CustomerCreditScore::class, 'current_credit_score_id');
    }

    /**
     * Flag this customer's score as stale so the daily job recalculates it.
     */
    public function markCreditScoreDirty(): void
    {
        if ($this->credit_score_dirty_at === null) {
            $this->forceFill(['credit_score_dirty_at' => now()])->saveQuietly();
        }
    }
}
