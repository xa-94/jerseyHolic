<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // M4 每日商品同步校验任务
        $schedule->job(new \App\Jobs\DailyProductSyncVerificationJob)
            ->dailyAt(config('product-sync.sync.daily_verification_time', '03:00'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
