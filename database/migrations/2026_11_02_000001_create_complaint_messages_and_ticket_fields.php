<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('client_request_id', 64)->nullable()->after('message');
            $table->string('content_hash', 64)->nullable()->after('client_request_id');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->unique(['user_id', 'client_request_id'], 'complaints_user_client_request_unique');
            $table->index(['user_id', 'content_hash', 'created_at'], 'complaints_user_content_created_index');
        });

        Schema::create('complaint_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_staff')->default(false);
            $table->timestamps();

            $table->index(['complaint_id', 'created_at']);
        });

        // Backfill existing tickets into a message thread.
        $complaints = DB::table('complaints')->orderBy('id')->get([
            'id',
            'user_id',
            'message',
            'admin_reply',
            'replied_by',
            'replied_at',
            'created_at',
            'updated_at',
        ]);

        foreach ($complaints as $complaint) {
            $hash = hash('sha256', mb_strtolower(trim((string) $complaint->message)));
            DB::table('complaints')->where('id', $complaint->id)->update([
                'content_hash' => $hash,
            ]);

            DB::table('complaint_messages')->insert([
                'complaint_id' => $complaint->id,
                'user_id' => $complaint->user_id,
                'body' => $complaint->message,
                'is_staff' => false,
                'created_at' => $complaint->created_at,
                'updated_at' => $complaint->created_at,
            ]);

            if (! empty($complaint->admin_reply)) {
                DB::table('complaint_messages')->insert([
                    'complaint_id' => $complaint->id,
                    'user_id' => $complaint->replied_by ?: $complaint->user_id,
                    'body' => $complaint->admin_reply,
                    'is_staff' => true,
                    'created_at' => $complaint->replied_at ?: $complaint->updated_at,
                    'updated_at' => $complaint->replied_at ?: $complaint->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_messages');

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropUnique('complaints_user_client_request_unique');
            $table->dropIndex('complaints_user_content_created_index');
            $table->dropColumn(['client_request_id', 'content_hash']);
        });
    }
};
