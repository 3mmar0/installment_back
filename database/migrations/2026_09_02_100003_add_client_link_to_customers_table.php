<?php

use App\Helpers\PhoneHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('client_account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('client_accounts')
                ->nullOnDelete();
            $table->string('phone_normalized')->nullable()->after('phone')->index();
            $table->index('client_account_id');
        });

        DB::table('customers')
            ->select(['id', 'phone'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $normalized = PhoneHelper::normalize($row->phone);
                    if ($normalized !== null) {
                        DB::table('customers')
                            ->where('id', $row->id)
                            ->update(['phone_normalized' => $normalized]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_account_id');
            $table->dropColumn('phone_normalized');
        });
    }
};
