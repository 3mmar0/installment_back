<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLimit extends Model
{
    use HasFactory;

    /**
     * Resources that support numeric usage tracking.
     *
     * @var array<int, string>
     */
    public const TRACKED_RESOURCES = [
        'customers',
        'installments',
        'notifications',
    ];

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'subscription_id',
        'subscription_assignment_id',
        'customers',
        'installments',
        'reports',
        'features',
        'customers_used',
        'installments_used',
        'notifications_used',
        'subscription_name',
        'subscription_slug',
        'currency',
        'price',
        'duration',
        'description',
        'start_date',
        'end_date',
        'status',
    ];

    /**
     * Attribute casts.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'customers' => 'array',
        'installments' => 'array',
        'notifications' => 'array',
        'features' => 'array',
        'reports' => 'boolean',
        'price' => 'float',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Scope the query to a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope the query to active limits.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where(function (Builder $inner) {
                $inner
                    ->whereNull('end_date')
                    ->orWhere('end_date', '>=', Carbon::today());
            });
    }

    /**
     * Determine whether the limit record is currently active.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->end_date instanceof Carbon) {
            return $this->end_date->isFuture() || $this->end_date->isToday();
        }

        return true;
    }

    /**
     * Check whether a user can create a resource of the given type.
     */
    public function canCreate(string $resourceType): bool
    {
        $limit = $this->getLimitCap($resourceType);

        if ($limit === null) {
            return true;
        }

        $used = $this->getUsedValue($resourceType);

        return $used < $limit;
    }

    /**
     * Get remaining count for the given resource.
     */
    public function getRemainingCount(string $resourceType): int
    {
        $limit = $this->getLimitCap($resourceType);

        if ($limit === null) {
            return PHP_INT_MAX;
        }

        $used = $this->getUsedValue($resourceType);

        return max($limit - $used, 0);
    }

    /**
     * Increment usage for the given resource.
     */
    public function incrementUsage(string $resourceType, int $count = 1): bool
    {
        if ($count < 1) {
            return true;
        }

        $column = $this->getUsageColumn($resourceType);

        if (!$column) {
            return false;
        }

        $limit = $this->getLimitCap($resourceType);
        $current = $this->{$column} ?? 0;

        if ($limit !== null && ($current + $count) > $limit) {
            return false;
        }

        $this->{$column} = $current + $count;

        return $this->save();
    }

    /**
     * Decrement usage for the given resource.
     */
    public function decrementUsage(string $resourceType, int $count = 1): bool
    {
        if ($count < 1) {
            return true;
        }

        $column = $this->getUsageColumn($resourceType);

        if (!$column) {
            return false;
        }

        $current = $this->{$column} ?? 0;
        $this->{$column} = max($current - $count, 0);

        return $this->save();
    }

    /**
     * Determine whether a feature is accessible under the current limits.
     */
    public function canAccessFeature(string $feature): bool
    {
        $features = $this->features ?? [];

        if (is_array($features)) {
            return (bool) ($features[$feature] ?? false);
        }

        return false;
    }

    /**
     * Resolve the configured cap for a given resource type.
     */
    protected function getLimitCap(string $resourceType): ?int
    {
        if (!in_array($resourceType, self::TRACKED_RESOURCES, true)) {
            return null;
        }

        $configuration = $this->{$resourceType} ?? null;

        if (is_array($configuration)) {
            if (array_key_exists('to', $configuration)) {
                return is_numeric($configuration['to']) ? (int) $configuration['to'] : null;
            }

            if (array_key_exists('max', $configuration)) {
                return is_numeric($configuration['max']) ? (int) $configuration['max'] : null;
            }
        }

        if (is_numeric($configuration)) {
            return (int) $configuration;
        }

        return null;
    }

    /**
     * Derive the \"used\" column for a resource.
     */
    protected function getUsageColumn(string $resourceType): ?string
    {
        if (!in_array($resourceType, self::TRACKED_RESOURCES, true)) {
            return null;
        }

        return "{$resourceType}_used";
    }

    /**
     * Fetch the used value for a resource.
     */
    protected function getUsedValue(string $resourceType): int
    {
        $column = $this->getUsageColumn($resourceType);

        if (!$column) {
            return 0;
        }

        return (int) ($this->{$column} ?? 0);
    }

    /**
     * Relationship: user owner.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function assignment()
    {
        return $this->belongsTo(SubscriptionAssignment::class, 'subscription_assignment_id');
    }
}
