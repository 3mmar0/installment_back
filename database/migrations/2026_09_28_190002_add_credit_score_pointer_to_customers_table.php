<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Pointer to the latest snapshot for fast list/dashboard reads.
            // No FK constraint so we never block customer/snapshot deletes.
            $table->unsignedBigInteger('current_credit_score_id')->nullable()->after('guarantor_phone');
            // Set when an event makes the score stale; the daily job targets these.
            $table->timestamp('credit_score_dirty_at')->nullable()->after('current_credit_score_id');

            $table->index('current_credit_score_id');
            $table->index('credit_score_dirty_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['current_credit_score_id']);
            $table->dropIndex(['credit_score_dirty_at']);
            $table->dropColumn(['current_credit_score_id', 'credit_score_dirty_at']);
        });
    }
};
