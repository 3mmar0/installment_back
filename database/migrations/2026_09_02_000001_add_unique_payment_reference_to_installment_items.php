<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Payment references are now minted server-side, so they can carry a uniqueness
     * guarantee and be used to detect a replayed payment.
     *
     * NULL references remain allowed and are not compared by a unique index, so
     * unpaid items are unaffected.
     */
    public function up(): void
    {
        Schema::table('installment_items', function (Blueprint $table) {
            $table->unique(
                ['installment_id', 'reference'],
                'installment_items_installment_reference_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('installment_items', function (Blueprint $table) {
            $table->dropUnique('installment_items_installment_reference_unique');
        });
    }
};
