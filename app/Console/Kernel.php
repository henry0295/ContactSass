<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

final class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Schedule campaign completion checks
        $schedule->call(function () {
            \Illuminate\Support\Facades\DB::table('campaigns')
                ->where('status', 'running')
                ->where('created_at', '<', now()->subHours(24))
                ->update(['status' => 'completed', 'completed_at' => now()]);
        })->everyMinute();

        // Schedule billing cycle processing
        $schedule->call(function () {
            // Close billing cycles and generate invoices
        })->monthly('1st 00:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
