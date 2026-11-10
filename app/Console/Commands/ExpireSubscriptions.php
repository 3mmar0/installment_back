<?php

namespace App\Console\Commands;

use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionEvent;
use App\Models\UserLimit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Expire subscription assignments whose end date has passed';

    public function handle(): int
    {
        $expired = SubscriptionAssignment::query()->where('status', 'active')->whereDate('end_date', '<', today())->get();
        foreach ($expired as $assignment) {
            DB::transaction(function () use ($assignment) {
                $assignment->refresh();
                if ($assignment->status !== 'active' || ! $assignment->end_date?->isBefore(today())) return;
                $assignment->update(['status' => 'expired', 'ended_reason' => 'expired']);
                UserLimit::query()->where('subscription_assignment_id', $assignment->id)->update(['status' => 'expired']);
                SubscriptionEvent::create(['assignment_id' => $assignment->id, 'user_id' => $assignment->user_id, 'type' => 'expired', 'payload' => ['end_date' => $assignment->end_date->toDateString()]]);
            });
        }
        $this->info("Expired {$expired->count()} subscription assignment(s).");
        return self::SUCCESS;
    }
}
