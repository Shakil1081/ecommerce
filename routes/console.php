<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Production needs ONE cron entry that runs the scheduler every minute:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
*/

// Return stock held by unpaid / abandoned orders to available inventory
Schedule::command('inventory:release-expired-reservations')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Abandoned-cart reminder SMS / WhatsApp sequence
Schedule::command('cart:process-recovery')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
