<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use App\Models\Subscription;
use App\Helpers\TrialHelper;

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
                'is_platform_admin' => true,
            ]);
        } else {
            $owner->update(['is_platform_admin' => true]);
        }

        TrialHelper::updateSettings(true, 7);

        if (!Subscription::where('slug', 'free')->exists()) {
            Subscription::create(array_merge(
                \App\Helpers\LimitsHelper::freePlanAttributes(),
                [
                    'slug' => 'free',
                    'created_by' => $owner->id,
                ]
            ));
        }

        \App\Helpers\LimitsHelper::syncAllUsersToFreePlan();
    }
}
