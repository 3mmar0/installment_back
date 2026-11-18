<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the duration enum on subscriptions to include quarterly and semi_annual
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN duration ENUM('monthly','quarterly','semi_annual','yearly') NOT NULL DEFAULT 'monthly'");

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('group_name')->nullable()->after('slug');
            $table->decimal('original_price', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['group_name', 'original_price']);
        });

        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN duration ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly'");
    }
};
