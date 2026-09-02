<?php

use App\Helpers\LimitsHelper;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // On a fresh database there is nobody to sync, and the free plan's
        // created_by cannot be resolved before the first user exists.
        if (! User::query()->exists()) {
            return;
        }

        LimitsHelper::syncAllUsersToFreePlan();
    }

    public function down(): void
    {
        // Irreversible data migration.
    }
};
