<?php

use App\Jobs\ProcessScheduledRemindersJob;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Schedule::job(new ProcessScheduledRemindersJob())
    ->dailyAt('08:00')
    ->name('payment-reminders')
    ->withoutOverlapping();

Schedule::job(new \App\Jobs\DailyCreditScoreRefreshJob())
    ->dailyAt((string) config('credit_score.recalculation.daily_at', '08:30'))
    ->name('credit-score-refresh')
    ->withoutOverlapping();

// Drop abandoned import previews (never confirmed) and their uploaded files.
Schedule::call(function () {
    ImportBatch::query()
        ->where('status', ImportBatch::STATUS_PREVIEWED)
        ->where('created_at', '<', now()->subDay())
        ->get()
        ->each(function (ImportBatch $batch) {
            if ($batch->file_path && Storage::disk('local')->exists($batch->file_path)) {
                Storage::disk('local')->delete($batch->file_path);
            }
            $batch->delete();
        });
})->dailyAt('03:00')->name('cleanup-import-previews')->withoutOverlapping();
