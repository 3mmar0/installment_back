<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Read admin-configured free plan duration (default: semi_annual = 6 months)
        $freeDuration = DB::table('app_settings')
            ->where('key', 'free_plan_duration')
            ->value('value') ?? 'semi_annual';

        $months = match ($freeDuration) {
            'monthly'    => 1,
            'quarterly'  => 3,
            'semi_annual' => 6,
            'yearly'     => 12,
            default      => 6,
        };

        // Fix user_limits: free plan rows with null end_date
        DB::table('user_limits')
            ->where('subscription_slug', 'free')
            ->whereNull('end_date')
            ->orderBy('id')
            ->each(function ($row) use ($months) {
                $start = $row->start_date
                    ? \Carbon\Carbon::parse($row->start_date)
                    : \Carbon\Carbon::now();

                DB::table('user_limits')
                    ->where('id', $row->id)
                    ->update(['end_date' => $start->copy()->addMonths($months)->toDateString()]);
            });

        // Fix subscription_assignments: free plan assignments with null end_date
        $freePlanId = DB::table('subscriptions')->where('slug', 'free')->value('id');

        if ($freePlanId) {
            DB::table('subscription_assignments')
                ->where('subscription_id', $freePlanId)
                ->whereNull('end_date')
                ->where('status', 'active')
                ->orderBy('id')
                ->each(function ($row) use ($months) {
                    $start = $row->start_date
                        ? \Carbon\Carbon::parse($row->start_date)
                        : \Carbon\Carbon::now();

                    DB::table('subscription_assignments')
                        ->where('id', $row->id)
                        ->update(['end_date' => $start->copy()->addMonths($months)->toDateString()]);
                });
        }

        // Also ensure the free subscription plan itself has the correct duration
        DB::table('subscriptions')
            ->where('slug', 'free')
            ->update(['duration' => 'semi_annual']);

        // Fix duration in user_limits for existing free plan users
        DB::table('user_limits')
            ->where('subscription_slug', 'free')
            ->update(['duration' => 'semi_annual']);
    }

    public function down(): void
    {
        // Reset free plan duration back to monthly (original default)
        DB::table('subscriptions')
            ->where('slug', 'free')
            ->update(['duration' => 'monthly']);

        // Clear end_dates set by this migration (can't distinguish from manually set ones)
        // so down() is intentionally a no-op for the user_limits rows
    }
};
