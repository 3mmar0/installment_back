<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Installment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Installment>
 */
class InstallmentFactory extends Factory
{
    protected $model = Installment::class;

    public function definition(): array
    {
        $months = 6;
        $start = Carbon::today();

        return [
            'user_id' => User::factory(),
            // Defaults to a customer owned by the same merchant, so a factory-built
            // installment is never accidentally cross-tenant.
            'customer_id' => fn (array $attributes) => Customer::factory()
                ->create(['user_id' => $attributes['user_id']])
                ->id,
            'name' => fake()->words(2, true),
            'total_amount' => 6000.00,
            'products' => [],
            'start_date' => $start,
            'months' => $months,
            'end_date' => $start->copy()->addMonths($months - 1),
            'status' => 'active',
            'notes' => null,
        ];
    }

    public function forMerchant(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'customer_id' => fn () => Customer::factory()->forMerchant($user)->create()->id,
        ]);
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn () => [
            'user_id' => $customer->user_id,
            'customer_id' => $customer->id,
        ]);
    }
}
