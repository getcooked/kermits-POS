<?php

use App\Models\DiningTable;
use App\Services\ReservationSchedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Subprocess fixture: the database path is always supplied by the isolated test.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1], 'queue.default' => 'sync']);
DB::purge('sqlite');
DB::statement('PRAGMA busy_timeout = 5000');

if ($argv[2] === 'setup') {
    Artisan::call('migrate', ['--force' => true]);
    DiningTable::query()->where('number', '!=', 1)->update(['active' => false]);
    exit(0);
}

$schedules = app(ReservationSchedule::class);
try {
    DB::transaction(function () use ($schedules, $argv) {
        $schedules->lock();
        usleep(250000); // Force the competing process to contend for the lock.
        $schedules->reserve([
            'reference' => 'RACE-'.$argv[2], 'type' => 'table', 'table_size' => 2,
            'customer_name' => 'Race test', 'email' => 'race@example.com', 'phone' => '09171234567',
            'guests' => 2, 'reservation_at' => now()->addDay()->setTime(12, 0)->format('Y-m-d H:i:s'),
        ]);
    });
    echo 'booked';
} catch (ValidationException $exception) {
    echo 'unavailable';
}
