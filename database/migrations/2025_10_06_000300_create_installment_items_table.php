<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('installment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_id')->constrained()->cascadeOnDelete();
            $table->date('due_date')->index();
            $table->decimal('amount', 12, 2);
            $table->timestamp('paid_at')->nullable()->index();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_items');
    }
};
