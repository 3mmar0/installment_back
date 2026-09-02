<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use App\Models\Subscription;
use App\Helpers\TrialHelper;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $owner = User::where('role', UserRole::Owner)->first();

        if (!$owner) {
            $email = (string) env('SEED_OWNER_EMAIL', '');
            $password = (string) env('SEED_OWNER_PASSWORD', '');

            // Refuse to invent credentials. A hardcoded owner password is a
            // published production login the moment the repository is shared.
            if ($email === '' || $password === '') {
                throw new RuntimeException(
                    'SEED_OWNER_EMAIL and SEED_OWNER_PASSWORD must be set to seed the owner account.'
                );
            }

            $owner = User::create([
                'name' => (string) env('SEED_OWNER_NAME', 'Owner'),
                'email' => $email,
                'password' => Hash::make($password),
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
