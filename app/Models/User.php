<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RegistrationSource;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'registration_source',
        'is_platform_admin',
        'trial_used_at',
        'last_active_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'trial_used_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'registration_source' => RegistrationSource::class,
            'is_platform_admin' => 'boolean',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function canManageComplaints(): bool
    {
        return $this->isOwner() || $this->isPlatformAdmin();
    }

    /**
     * Record that this user is using the system.
     *
     * Writes are throttled so busy API traffic does not update the row on every request.
     */
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

    public function isRecentlyActive(int $days = 7): bool
    {
        return $this->last_active_at?->gte(now()->subDays($days)) ?? false;
    }

    public function isPlatformAdmin(): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        $emails = config('app.platform_admin_emails', []);

        return in_array($this->email, $emails, true);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function installments()
    {
        return $this->hasMany(Installment::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }

    public function userLimit()
    {
        return $this->hasOne(UserLimit::class);
    }

    public function subscriptionAssignments()
    {
        return $this->hasMany(SubscriptionAssignment::class);
    }

    public function currentSubscriptionAssignment()
    {
        return $this->hasOne(SubscriptionAssignment::class)->latestOfMany();
    }
}
