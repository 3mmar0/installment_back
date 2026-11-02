<?php

it('hides platform category from regular merchants', function () {
    actingAsMerchant();

    $this->getJson('/api/reports/catalog')
        ->assertOk()
        ->assertJsonPath('data.platform_enabled', false)
        ->assertJsonMissing(['key' => 'platform_inactive_candidates']);
});

it('exposes platform category to platform admins', function () {
    actingAsPlatformAdmin();

    $this->getJson('/api/reports/catalog')
        ->assertOk()
        ->assertJsonPath('data.platform_enabled', true)
        ->assertJsonFragment(['key' => 'platform_overview'])
        ->assertJsonFragment(['key' => 'platform_inactive_candidates'])
        ->assertJsonFragment(['key' => 'platform_resource_usage']);
});

it('forbids platform reports for merchants', function () {
    actingAsMerchant();

    $this->getJson('/api/reports/platform_overview')
        ->assertStatus(403);
});

it('returns platform overview kpis for admins', function () {
    actingAsPlatformAdmin();
    merchantWithPlan();
    merchantWithPlan();

    $this->getJson('/api/reports/platform_overview')
        ->assertOk()
        ->assertJsonPath('data.summary.merchants_total', 2)
        ->assertJsonStructure([
            'data' => [
                'summary' => [
                    'merchants_total',
                    'active_7d',
                    'active_30d',
                    'inactive_5m',
                    'customers_used_total',
                    'installments_used_total',
                ],
                'rows',
                'columns',
                'meta',
            ],
        ]);
});

it('includes long-inactive merchants as delete candidates and excludes recent ones', function () {
    actingAsPlatformAdmin();

    $dormant = merchantWithPlan();
    $dormant->forceFill([
        'last_active_at' => now()->subMonths(6),
        'created_at' => now()->subMonths(8),
    ])->save();

    $active = merchantWithPlan();
    $active->forceFill([
        'last_active_at' => now()->subDay(),
        'created_at' => now()->subMonths(8),
    ])->save();

    $response = $this->getJson('/api/reports/platform_inactive_candidates?per_page=50')
        ->assertOk();

    $ids = collect($response->json('data.rows'))->pluck('id')->all();

    expect($ids)->toContain($dormant->id)
        ->and($ids)->not->toContain($active->id)
        ->and((int) $response->json('data.summary.count'))->toBeGreaterThanOrEqual(1);
});

it('includes platform summary in overview for admins only', function () {
    actingAsPlatformAdmin();
    merchantWithPlan();

    $this->getJson('/api/reports/overview')
        ->assertOk()
        ->assertJsonPath('data.platform.merchants_total', 1)
        ->assertJsonStructure([
            'data' => [
                'platform' => [
                    'merchants_total',
                    'active_7d',
                    'inactive_5m',
                ],
            ],
        ]);

    actingAsMerchant();
    $this->getJson('/api/reports/overview')
        ->assertOk()
        ->assertJsonPath('data.platform', null);
});

it('exports inactive candidates as csv for platform admin', function () {
    actingAsPlatformAdmin();
    $dormant = merchantWithPlan();
    $dormant->forceFill([
        'last_active_at' => now()->subMonths(7),
        'created_at' => now()->subYear(),
    ])->save();

    $this->postJson('/api/reports/platform_inactive_candidates/export', [
        'format' => 'csv',
    ])->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
