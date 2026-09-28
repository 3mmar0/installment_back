<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('monthly_salary', 12, 2)->nullable()->after('job');
        });

        \Illuminate\Support\Facades\DB::table('customers')
            ->whereNotIn('id', function ($q) {
                $q->select('customer_id')->from('installments')->distinct();
            })
            ->update([
                'current_credit_score_id' => null,
                'credit_score_dirty_at' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('monthly_salary');
        });
    }
};
