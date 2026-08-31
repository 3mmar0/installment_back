<?php

use App\Jobs\ProcessScheduledRemindersJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ProcessScheduledRemindersJob())
    ->dailyAt('08:00')
    ->name('payment-reminders')
    ->withoutOverlapping();
