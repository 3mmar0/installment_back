<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FEATURE_KEY = 'internal_credit_score';

    public function up(): void
    {
        $this->applyToTable('subscriptions');
        $this->applyToTable('user_limits');
        $this->applyToTable('subscription_assignments');
    }

    public function down(): void
    {
        // Intentionally no rollback — re-enabling for all merchants is not desired.
    }

    private function applyToTable(string $table): void
    {
        if (! $this->tableHasColumn($table, 'features')) {
            return;
        }

        DB::table($table)
            ->select(['id', 'features'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $features = $this->decodeFeatures($row->features);
                    $features[self::FEATURE_KEY] = false;

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['features' => json_encode($features)]);
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeFeatures(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }
};
