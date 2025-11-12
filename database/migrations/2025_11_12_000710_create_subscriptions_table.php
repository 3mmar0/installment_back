<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('currency', 10)->default('EGP');
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('duration', ['monthly', 'yearly'])->default('monthly');
            $table->text('description')->nullable();
            $table->json('customers')->nullable();
            $table->json('installments')->nullable();
            $table->json('notifications')->nullable();
            $table->boolean('reports')->default(true);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
