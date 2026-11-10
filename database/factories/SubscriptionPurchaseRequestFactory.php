<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionPurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPurchaseRequestFactory extends Factory
{
    protected $model = SubscriptionPurchaseRequest::class;

    public function definition(): array
    {
        $plan = Subscription::factory();

        return [
            'user_id' => User::factory(),
            'subscription_id' => $plan,
            'plan_snapshot' => ['name' => 'Professional', 'price' => 199, 'currency' => 'EGP', 'duration' => 'monthly', 'features' => []],
            'status' => 'pending_review',
            'payment_reference' => fake()->unique()->bothify('REF-#####'),
            'requested_at' => now(),
        ];
    }
}
