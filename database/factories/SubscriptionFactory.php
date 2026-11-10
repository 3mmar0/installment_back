<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'name' => 'Professional', 'slug' => fake()->unique()->slug(2), 'currency' => 'EGP',
            'price' => 199, 'duration' => 'monthly', 'customers' => ['to' => 100],
            'installments' => ['to' => 200], 'notifications' => ['to' => 1000],
            'reports' => true, 'features' => ['basic_reports' => true], 'is_active' => true,
            'created_by' => User::factory()->state(['role' => UserRole::Owner]),
        ];
    }
}
