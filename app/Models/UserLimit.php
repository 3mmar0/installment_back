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
        'assistant_messages',
        'whatsapp_messages',
    ];

    /**
     * Monthly counter resources (reset when usage_period changes).
     *
     * @var array<int, string>
     */
    public const MONTHLY_RESOURCES = [
        'assistant_messages',
        'whatsapp_messages',
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'subscription_id',
        'subscription_assignment_id',
        'customers',
        'installments',
        'notifications',
        'assistant_messages',
        'whatsapp_messages',
        'reports',
        'features',
        'customers_used',
        'installments_used',
        'notifications_used',
        'assistant_messages_used',
        'whatsapp_messages_used',
        'assistant_messages_bonus',
        'whatsapp_messages_bonus',
        'usage_period',
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
     * @var array<string, string>
     */
    protected $casts = [
        'customers' => 'array',
        'installments' => 'array',
        'notifications' => 'array',
        'assistant_messages' => 'array',
        'whatsapp_messages' => 'array',
        'features' => 'array',
        'reports' => 'boolean',
        'price' => 'float',
        'start_date' => 'date',
        'end_date' => 'date',
        'assistant_messages_used' => 'integer',
        'whatsapp_messages_used' => 'integer',
        'assistant_messages_bonus' => 'integer',
        'whatsapp_messages_bonus' => 'integer',
    ];

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

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

    public function refreshMonthlyPeriodIfNeeded(): void
    {
        $period = now()->format('Y-m');
        if ($this->usage_period === $period) {
            return;
        }

        $this->forceFill([
            'assistant_messages_used' => 0,
            'whatsapp_messages_used' => 0,
            'usage_period' => $period,
        ])->save();
    }

    public function canCreate(string $resourceType): bool
    {
        if (in_array($resourceType, self::MONTHLY_RESOURCES, true)) {
            $this->refreshMonthlyPeriodIfNeeded();
        }

        $limit = $this->getEffectiveCap($resourceType);

        if ($limit === null) {
            return true;
        }

        return $this->getUsedValue($resourceType) < $limit;
    }

    public function getRemainingCount(string $resourceType): int
    {
        if (in_array($resourceType, self::MONTHLY_RESOURCES, true)) {
            $this->refreshMonthlyPeriodIfNeeded();
        }

        $limit = $this->getEffectiveCap($resourceType);

        if ($limit === null) {
            return PHP_INT_MAX;
        }

        return max($limit - $this->getUsedValue($resourceType), 0);
    }

    public function incrementUsage(string $resourceType, int $count = 1): bool
    {
        if ($count < 1) {
            return true;
        }

        if (in_array($resourceType, self::MONTHLY_RESOURCES, true)) {
            $this->refreshMonthlyPeriodIfNeeded();
        }

        $column = $this->getUsageColumn($resourceType);

        if (! $column) {
            return false;
        }

        $limit = $this->getEffectiveCap($resourceType);
        $current = $this->{$column} ?? 0;

        if ($limit !== null && ($current + $count) > $limit) {
            return false;
        }

        $this->{$column} = $current + $count;

        return $this->save();
    }

    public function decrementUsage(string $resourceType, int $count = 1): bool
    {
        if ($count < 1) {
            return true;
        }

        $column = $this->getUsageColumn($resourceType);

        if (! $column) {
            return false;
        }

        $current = $this->{$column} ?? 0;
        $this->{$column} = max($current - $count, 0);

        return $this->save();
    }

    public function canAccessFeature(string $feature): bool
    {
        $features = $this->features ?? [];

        if (is_array($features)) {
            if (! empty($features['all'])) {
                return true;
            }

            return (bool) ($features[$feature] ?? false);
        }

        return false;
    }

    public function getBonus(string $resourceType): int
    {
        if (! in_array($resourceType, self::MONTHLY_RESOURCES, true)) {
            return 0;
        }

        return (int) ($this->{"{$resourceType}_bonus"} ?? 0);
    }

    public function addBonus(string $resourceType, int $amount): bool
    {
        if ($amount < 1 || ! in_array($resourceType, self::MONTHLY_RESOURCES, true)) {
            return false;
        }

        $column = "{$resourceType}_bonus";
        $this->{$column} = $this->getBonus($resourceType) + $amount;

        return $this->save();
    }

    public function resetMonthlyUsage(bool $clearBonus = false): void
    {
        $payload = [
            'assistant_messages_used' => 0,
            'whatsapp_messages_used' => 0,
            'usage_period' => now()->format('Y-m'),
        ];

        if ($clearBonus) {
            $payload['assistant_messages_bonus'] = 0;
            $payload['whatsapp_messages_bonus'] = 0;
        }

        $this->forceFill($payload)->save();
    }

    /**
     * Base cap from plan/override JSON (without bonus).
     */
    protected function getLimitCap(string $resourceType): ?int
    {
        if (! in_array($resourceType, self::TRACKED_RESOURCES, true)) {
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
     * Effective cap including gift bonus for monthly messaging resources.
     */
    public function getEffectiveCap(string $resourceType): ?int
    {
        $base = $this->getLimitCap($resourceType);

        if ($base === null) {
            return null;
        }

        return $base + $this->getBonus($resourceType);
    }

    protected function getUsageColumn(string $resourceType): ?string
    {
        if (! in_array($resourceType, self::TRACKED_RESOURCES, true)) {
            return null;
        }

        return "{$resourceType}_used";
    }

    protected function getUsedValue(string $resourceType): int
    {
        $column = $this->getUsageColumn($resourceType);

        if (! $column) {
            return 0;
        }

        return (int) ($this->{$column} ?? 0);
    }

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
