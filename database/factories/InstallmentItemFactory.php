<?php

namespace Database\Factories;

use App\Models\Installment;
use App\Models\InstallmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InstallmentItem>
 */
class InstallmentItemFactory extends Factory
{
    protected $model = InstallmentItem::class;

    public function definition(): array
    {
        return [
            'installment_id' => Installment::factory(),
            'due_date' => Carbon::today(),
            'amount' => 1000.00,
            'paid_at' => null,
            'paid_amount' => null,
            'status' => 'pending',
            'reference' => null,
            'note' => null,
        ];
    }

    public function forInstallment(Installment $installment): static
    {
        return $this->state(fn () => ['installment_id' => $installment->id]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => Carbon::now(),
            'paid_amount' => $attributes['amount'] ?? 1000.00,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => 'overdue',
            'due_date' => Carbon::today()->subMonth(),
        ]);
    }
}
