<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionTrialTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_plan_trial_can_only_be_started_once(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $merchant = User::factory()->create(['role' => UserRole::User]);
        $plan = Subscription::factory()->create(['created_by' => $owner->id, 'price' => 199]);
        Sanctum::actingAs($merchant);

        $this->postJson('/api/trials/start', ['subscription_id' => $plan->id])
            ->assertOk()->assertJsonPath('data.is_trial', true);
        $this->postJson('/api/trials/start', ['subscription_id' => $plan->id])->assertStatus(422);

        $this->assertNotNull($merchant->fresh()->trial_used_at);
    }

    public function test_trial_rejects_free_plan(): void
    {
        $merchant = User::factory()->create(['role' => UserRole::User]);
        $free = Subscription::factory()->create(['price' => 0]);
        Sanctum::actingAs($merchant);

        $this->postJson('/api/trials/start', ['subscription_id' => $free->id])->assertStatus(422);
    }
}
