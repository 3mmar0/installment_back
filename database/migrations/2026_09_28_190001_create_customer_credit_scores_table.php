<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_credit_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // Owning merchant, denormalised for fast per-tenant analytics.
            $table->foreignId('user_id')->nullable()->index();

            // Query columns (kept out of JSON on purpose).
            $table->unsignedSmallInteger('score');            // 300..850
            $table->decimal('raw_score', 6, 3);               // 0..100
            $table->string('risk_level')->index();
            $table->string('confidence_level')->index();
            $table->smallInteger('score_change')->default(0); // vs previous snapshot

            // Component scores (0..100), null when a component was unavailable.
            $table->decimal('payment_history_score', 6, 3)->nullable();
            $table->decimal('financial_burden_score', 6, 3)->nullable();
            $table->decimal('credit_history_score', 6, 3)->nullable();
            $table->decimal('credit_activity_score', 6, 3)->nullable();
            $table->decimal('payment_consistency_score', 6, 3)->nullable();
            $table->decimal('other_risk_score', 6, 3)->nullable();

            // Frequently filtered financial snapshots.
            $table->decimal('current_outstanding', 14, 2)->default(0);
            $table->decimal('current_overdue_amount', 14, 2)->default(0);
            $table->unsignedInteger('current_overdue_count')->default(0);
            $table->unsignedInteger('max_dpd')->default(0);
            $table->unsignedInteger('current_max_dpd')->default(0);
            $table->unsignedInteger('active_contracts')->default(0);
            $table->unsignedInteger('completed_contracts')->default(0);
            $table->unsignedSmallInteger('history_months')->default(0);
            $table->boolean('thin_file')->default(false)->index();

            // Rich, non-queried payloads.
            $table->json('metrics')->nullable();
            $table->json('positive_factors')->nullable();
            $table->json('negative_factors')->nullable();
            $table->json('change_reasons')->nullable();
            $table->json('configuration_snapshot')->nullable();

            $table->foreignId('model_version_id')->constrained('credit_score_model_versions');
            $table->string('source')->default('actual'); // actual | reconstructed
            $table->timestamp('calculated_at')->index();
            $table->timestamps();

            $table->index(['customer_id', 'calculated_at']);
            $table->index(['user_id', 'score']);
            $table->index(['user_id', 'risk_level']);
            $table->index(['user_id', 'calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credit_scores');
    }
};
