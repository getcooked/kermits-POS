<?php

use App\Services\ReservationSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('reservations:expire', function () {
    $count = app(ReservationSchedule::class)->expireHolds();
    $this->info("Expired {$count} reservation holds.");
})->purpose('Release pending reservations after their approval deadline');

Schedule::command('reservations:expire')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
