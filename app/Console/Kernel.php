<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('reminders:send')->dailyAt('09:00');
        // Schedule the completely free automated WhatsApp reminders
        // Runs on the last day of every month at 10:00 AM automatically
        $schedule->command('debts:send-monthly-reminders')->monthlyOn(date('t'), '10:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
