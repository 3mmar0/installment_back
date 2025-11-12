<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_active_subscription_plans(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        Subscription::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'currency' => 'EGP',
            'price' => 0,
            'duration' => 'monthly',
            'customers' => ['from' => 0, 'to' => 10],
            'installments' => ['from' => 0, 'to' => 20],
            'notifications' => ['from' => 0, 'to' => 100],
            'reports' => true,
            'features' => ['basic_reports' => true],
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $response = $this->getJson('/api/subscriptions');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    [
                        'id',
                        'name',
                        'slug',
                        'customers',
                        'installments',
                        'notifications',
                    ],
                ],
            ]);
    }

    public function test_owner_can_assign_subscription_to_user(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $user = User::factory()->create(['role' => UserRole::User]);

        $subscription = Subscription::create([
            'name' => 'Professional',
            'slug' => 'professional',
            'currency' => 'EGP',
            'price' => 199.99,
            'duration' => 'monthly',
            'customers' => ['from' => 0, 'to' => 100],
            'installments' => ['from' => 0, 'to' => 200],
            'notifications' => ['from' => 0, 'to' => 1000],
            'reports' => true,
            'features' => ['advanced_reports' => true],
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/subscriptions/{$subscription->id}/assign", [
            'user_id' => $user->id,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('user_limits', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);
    }
}
