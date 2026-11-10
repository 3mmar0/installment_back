<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionPurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionPurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    private function plan(float $price = 199): Subscription
    {
        return Subscription::factory()->create(['price' => $price]);
    }

    public function test_public_catalogue_includes_active_paid_plans(): void
    {
        $paid = $this->plan();
        $inactive = Subscription::factory()->create(['price' => 299, 'is_active' => false]);

        $response = $this->getJson('/api/subscriptions-public');

        $response->assertOk()->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $paid->id])
            ->assertJsonMissing(['id' => $inactive->id]);
    }

    public function test_merchant_cannot_self_activate_paid_plan_and_can_create_purchase_request(): void
    {
        $merchant = User::factory()->create(['role' => UserRole::User]);
        $paid = $this->plan();
        Sanctum::actingAs($merchant);

        $this->postJson("/api/subscriptions/{$paid->id}/change", ['status' => 'active', 'features' => ['all' => true]])->assertStatus(410);
        $this->postJson('/api/subscription-purchase-requests', ['subscription_id' => $paid->id, 'payment_reference' => 'receipt-1'])
            ->assertOk()->assertJsonPath('data.status', 'pending_review');

        $this->assertDatabaseHas('subscription_purchase_requests', ['user_id' => $merchant->id, 'subscription_id' => $paid->id, 'status' => 'pending_review']);
        $this->assertDatabaseMissing('subscription_assignments', ['user_id' => $merchant->id, 'subscription_id' => $paid->id, 'status' => 'active']);
    }

    public function test_owner_approval_activates_snapshot_exactly_once(): void
    {
        $merchant = User::factory()->create(['role' => UserRole::User]);
        $paid = $this->plan();
        $purchase = SubscriptionPurchaseRequest::create([
            'user_id' => $merchant->id, 'subscription_id' => $paid->id,
            'plan_snapshot' => app(\App\Services\SubscriptionEntitlementService::class)->snapshot($paid),
            'status' => 'pending_review', 'requested_at' => now(),
        ]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/admin/subscription-purchase-requests/{$purchase->id}/approve")->assertOk();
        $this->postJson("/api/admin/subscription-purchase-requests/{$purchase->id}/approve")->assertOk();

        $this->assertSame(1, SubscriptionAssignment::where('user_id', $merchant->id)->where('status', 'active')->count());
        $this->assertDatabaseHas('subscription_purchase_requests', ['id' => $purchase->id, 'status' => 'approved']);
    }
}
