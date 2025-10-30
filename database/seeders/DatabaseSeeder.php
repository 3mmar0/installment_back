<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (!User::where('role', UserRole::Owner)->exists()) {
            User::create([
                'name' => 'Owner',
                'email' => 'superadmin@admin.com',
                'password' => Hash::make('password'),
                'role' => UserRole::Owner,
            ]);
        }

        $this->call([
            PlanSeeder::class,
        ]);
    }
}
