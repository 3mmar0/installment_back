<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class ClientAccount extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'phone_normalized',
        'password',
        'email_verified_at',
        'last_active_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function markAsActive(int $throttleMinutes = 5): bool
    {
        if (
            $throttleMinutes > 0
            && $this->last_active_at
            && $this->last_active_at->gt(now()->subMinutes($throttleMinutes))
        ) {
            return false;
        }

        $now = now();

        $this->newQuery()
            ->whereKey($this->getKey())
            ->update(['last_active_at' => $now]);

        $this->last_active_at = $now;

        return true;
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'client_account_id');
    }

    public function personalInstallments()
    {
        return $this->hasMany(Installment::class, 'client_account_id');
    }

    public function paymentRequests()
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function emailVerifications()
    {
        return $this->hasMany(ClientEmailVerification::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'client_account_id');
    }

    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }
}
