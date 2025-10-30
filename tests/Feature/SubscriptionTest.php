<?php

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('lists public plans', function () {
    $this->seed(PlanSeeder::class);

    $response = $this->getJson('/api/plans');

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);
});

it('subscribes to a plan when authenticated', function () {
    $this->seed(PlanSeeder::class);
    $user = User::factory()->create();
    $plan = Plan::first();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/subscriptions/subscribe', [
        'plan_id' => $plan->id,
    ]);

    $response
        ->assertCreated()
        ->assertJson([
            'success' => true,
        ]);
});
