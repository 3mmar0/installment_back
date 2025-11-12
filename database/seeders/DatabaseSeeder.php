<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use App\Models\Subscription;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $owner = User::where('role', UserRole::Owner)->first();

        if (!$owner) {
            $owner = User::create([
                'name' => 'Owner',
                'email' => 'superadmin@admin.com',
                'password' => Hash::make('password'),
                'role' => UserRole::Owner,
            ]);
        }

        if (!Subscription::where('slug', 'free')->exists()) {
            Subscription::create([
                'name' => 'Free Plan',
                'slug' => 'free',
                'currency' => 'EGP',
                'price' => 0,
                'duration' => 'monthly',
                'description' => 'Default starter plan with limited allowances.',
                'customers' => ['from' => 0, 'to' => 10],
                'installments' => ['from' => 0, 'to' => 20],
                'notifications' => ['from' => 0, 'to' => 200],
                'reports' => true,
                'features' => ['basic_reports' => true],
                'is_active' => true,
                'created_by' => $owner->id,
            ]);
        }
    }
}
