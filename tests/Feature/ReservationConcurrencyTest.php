<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReservationConcurrencyTest extends TestCase
{
    public function test_two_processes_cannot_reserve_the_last_table(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'reservation-race-');
        $worker = base_path('tests/Support/reservation-race-worker.php');
        $processes = [];
        try {
            $setup = new Process([PHP_BINARY, $worker, $database, 'setup'], base_path());
            $setup->mustRun();
            foreach (['first', 'second'] as $name) {
                $process = new Process([PHP_BINARY, $worker, $database, $name], base_path());
                $process->start();
                $processes[] = $process;
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $results[] = trim($process->getOutput());
            }
            sort($results);
            $this->assertSame(['booked', 'unavailable'], $results);
            $databaseConnection = new \PDO('sqlite:'.$database);
            $this->assertSame(1, (int) $databaseConnection->query('SELECT COUNT(*) FROM reservations')->fetchColumn());
            $databaseConnection = null;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            unlink($database);
        }
    }
}
