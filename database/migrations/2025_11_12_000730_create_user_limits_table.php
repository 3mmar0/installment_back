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
        Schema::create('user_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('subscription_assignment_id')->nullable()->constrained('subscription_assignments')->nullOnDelete();

            $table->json('customers')->nullable();
            $table->json('installments')->nullable();
            $table->json('notifications')->nullable();
            $table->boolean('reports')->default(false);
            $table->json('features')->nullable();

            $table->integer('customers_used')->default(0);
            $table->integer('installments_used')->default(0);
            $table->integer('notifications_used')->default(0);

            $table->string('subscription_name')->nullable();
            $table->string('subscription_slug')->nullable();
            $table->string('currency')->default('EGP');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('duration')->default('monthly');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active');

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('subscription_slug');
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_limits');
    }
};
