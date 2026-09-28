<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('credit_score_model_versions', function (Blueprint $table) {
            $table->id();
            // Human identifier, e.g. "V1". Unique so snapshots can reference it.
            $table->string('version')->unique();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            // Frozen copy of config/credit_score.php at publish time. Every
            // snapshot points at this row so old scores stay reproducible.
            $table->json('configuration');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('effective_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_score_model_versions');
    }
};
