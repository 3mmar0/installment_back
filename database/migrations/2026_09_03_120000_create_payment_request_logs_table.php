<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_request_id')->constrained('payment_requests')->cascadeOnDelete();
            $table->string('action', 32);
            $table->date('paid_on')->nullable();
            $table->text('note')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedInteger('attachment_size')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_client_id')->nullable()->constrained('client_accounts')->nullOnDelete();
            $table->timestamps();

            $table->index(['payment_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_request_logs');
    }
};
