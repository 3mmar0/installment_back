<?php

use App\Helpers\LimitsHelper;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        LimitsHelper::syncAllUsersToFreePlan();
    }

    public function down(): void
    {
        // Irreversible data migration.
    }
};
