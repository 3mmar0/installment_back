<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('whatsapp_auto_reminders')->default(false)->after('guarantor_phone');
        });

        Schema::create('whatsapp_daily_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('charge_date');
            $table->unsignedInteger('messages_sent')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'customer_id', 'charge_date'], 'whatsapp_daily_charges_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_daily_charges');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('whatsapp_auto_reminders');
        });
    }
};
