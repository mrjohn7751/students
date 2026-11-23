<?php

use Illuminate\Support\Facades\Schedule;

// HEMIS'dan reyestrni har kecha yangilash (cron: * * * * * php artisan schedule:run)
Schedule::command('hemis:sync')->dailyAt('02:30')->withoutOverlapping();
