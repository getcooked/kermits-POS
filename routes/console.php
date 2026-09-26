<?php

use App\Services\ReservationSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('reservations:expire', function () {
    $count = app(ReservationSchedule::class)->expireHolds();
    $this->info("Expired {$count} reservation holds.");
})->purpose('Release pending reservations after their approval deadline');

Schedule::command('reservations:expire')->everyMinute()->withoutOverlapping();

Artisan::command('mail:diagnose {to? : Address to receive the test emails}', function (?string $to = null) {
    $to ??= config('mail.from.address');
    $this->line('Default mailer: '.config('mail.default'));

    foreach (['smtp', 'smtp_ssl'] as $mailer) {
        $config = config("mail.mailers.{$mailer}");
        $this->line("{$mailer}: {$config['host']}:{$config['port']} as ".($config['username'] ?: '(no username)'));

        try {
            Mail::mailer($mailer)->raw("Kermit's mail test via {$mailer}.", fn ($message) => $message->to($to)->subject("Kermit's mail test ({$mailer})"));
            $this->info("  Sent to {$to}.");
        } catch (Throwable $exception) {
            $this->error('  '.$exception::class.': '.$exception->getMessage());
        }
    }
})->purpose('Send a test email through each SMTP mailer and print any errors');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
