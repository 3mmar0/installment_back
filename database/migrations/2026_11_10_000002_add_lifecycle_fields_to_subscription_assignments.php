<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_assignments', function (Blueprint $table) {
            $table->string('source')->default('legacy')->after('status');
            $table->json('snapshot')->nullable()->after('features');
            $table->foreignId('activated_by')->nullable()->after('snapshot')->constrained('users')->nullOnDelete();
            $table->string('ended_reason')->nullable()->after('activated_by');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activated_by');
            $table->dropColumn(['source', 'snapshot', 'ended_reason']);
        });
    }
};
