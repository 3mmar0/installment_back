<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->json('assistant_messages')->nullable()->after('notifications');
            $table->json('whatsapp_messages')->nullable()->after('assistant_messages');
        });

        Schema::table('user_limits', function (Blueprint $table) {
            $table->json('assistant_messages')->nullable()->after('notifications');
            $table->json('whatsapp_messages')->nullable()->after('assistant_messages');
            $table->unsignedInteger('assistant_messages_used')->default(0)->after('notifications_used');
            $table->unsignedInteger('whatsapp_messages_used')->default(0)->after('assistant_messages_used');
            $table->unsignedInteger('assistant_messages_bonus')->default(0)->after('whatsapp_messages_used');
            $table->unsignedInteger('whatsapp_messages_bonus')->default(0)->after('assistant_messages_bonus');
            $table->string('usage_period', 7)->nullable()->after('whatsapp_messages_bonus');
        });

        Schema::create('merchant_gifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resource', 64);
            $table->unsignedInteger('amount');
            $table->string('source', 32)->default('gift');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'resource']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_gifts');

        Schema::table('user_limits', function (Blueprint $table) {
            $table->dropColumn([
                'assistant_messages',
                'whatsapp_messages',
                'assistant_messages_used',
                'whatsapp_messages_used',
                'assistant_messages_bonus',
                'whatsapp_messages_bonus',
                'usage_period',
            ]);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['assistant_messages', 'whatsapp_messages']);
        });
    }
};
