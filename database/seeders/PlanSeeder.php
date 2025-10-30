<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        if (Plan::count() > 0) {
            return;
        }

        Plan::create([
            'name' => 'Basic Monthly',
            'price_cents' => 9900,
            'currency' => 'USD',
            'interval' => 'monthly',
            'trial_days' => 7,
            'features' => ['support' => 'email', 'projects' => 5],
            'is_active' => true,
        ]);

        Plan::create([
            'name' => 'Pro Yearly',
            'price_cents' => 89900,
            'currency' => 'USD',
            'interval' => 'yearly',
            'trial_days' => null,
            'features' => ['support' => 'priority', 'projects' => 'unlimited'],
            'is_active' => true,
        ]);
    }
}
