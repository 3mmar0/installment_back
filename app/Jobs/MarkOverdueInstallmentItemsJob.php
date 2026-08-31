<?php

namespace App\Jobs;

use App\Models\InstallmentItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkOverdueInstallmentItemsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(): void
    {
        InstallmentItem::query()
            ->whereNull('paid_at')
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', now()->startOfDay())
            ->chunkById(500, function ($items) {
                foreach ($items as $item) {
                    if ($item->status !== 'overdue') {
                        $item->update(['status' => 'overdue']);
                    }
                }
            });
    }
}
