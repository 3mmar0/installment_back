<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('national_id', 14)->nullable()->after('phone_normalized');
            $table->string('guarantor_name')->nullable()->after('notes');
            $table->string('guarantor_national_id', 14)->nullable()->after('guarantor_name');
            $table->string('guarantor_phone', 50)->nullable()->after('guarantor_national_id');

            $table->unique(['user_id', 'national_id'], 'customers_user_national_id_unique');
            $table->index('national_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_user_national_id_unique');
            $table->dropIndex(['national_id']);
            $table->dropColumn([
                'national_id',
                'guarantor_name',
                'guarantor_national_id',
                'guarantor_phone',
            ]);
        });
    }
};
