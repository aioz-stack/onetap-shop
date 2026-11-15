<?php

use App\Services\Finance\EarningsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Move seller earnings from pending to available once the hold period
| (Admin → Settings → Finance → Earnings hold) has passed.
*/
Artisan::command('onetap:release-earnings', function (EarningsService $earnings) {
    $released = $earnings->releaseDue();
    $this->info("Released earnings for {$released} order(s).");
})->purpose('Release seller earnings whose hold period has ended');

Schedule::command('onetap:release-earnings')->hourly()->withoutOverlapping();
