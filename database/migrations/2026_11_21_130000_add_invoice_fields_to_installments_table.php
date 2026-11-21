<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->decimal('invoice_price', 12, 2)->nullable()->after('total_amount');
            $table->decimal('down_payment', 12, 2)->nullable()->after('invoice_price');
            $table->string('attachment_path', 500)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->dropColumn(['invoice_price', 'down_payment', 'attachment_path']);
        });
    }
};
