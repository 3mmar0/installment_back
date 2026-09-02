<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a merchant with an active plan.
 *
 * Customer and installment routes sit behind EnsureActiveSubscription, so a plain
 * User::factory() user is rejected with 402 before reaching the controller.
 *
 * @param  array<string, mixed>  $limits
 */
function merchantWithPlan(array $limits = []): App\Models\User
{
    $user = App\Models\User::factory()->create(['role' => App\Enums\UserRole::User]);

    App\Helpers\LimitsHelper::createOrUpdateUserLimits($user->id, array_merge([
        'subscription_name' => 'Test Plan',
        'subscription_slug' => 'test-plan',
        'customers' => ['from' => 0, 'to' => 100],
        'installments' => ['from' => 0, 'to' => 100],
        'notifications' => ['from' => 0, 'to' => 100],
        'reports' => true,
        'features' => ['basic_reports' => true],
        'status' => 'active',
    ], $limits));

    return $user->refresh();
}

/**
 * Create a merchant with an active plan and authenticate as them.
 *
 * @param  array<string, mixed>  $limits
 */
function actingAsMerchant(array $limits = []): App\Models\User
{
    $user = merchantWithPlan($limits);

    Laravel\Sanctum\Sanctum::actingAs($user);

    return $user;
}

/**
 * Create an authenticated merchant whose plan cap for $resource is already exhausted.
 */
function actingAsMerchantAtCap(string $resource): App\Models\User
{
    return actingAsMerchant([$resource => ['from' => 0, 'to' => 0]]);
}
