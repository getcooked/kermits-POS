<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Services\ReservationSchedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegacyReservationSchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2030, 1, 1)->setTime(9, 0));

        Schema::dropIfExists('reservation_locks');
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_period_index');
            $table->dropColumn(['reservation_end_at', 'hold_expires_at']);
        });
        Schema::table('reservations', function (Blueprint $table) {
            $table->unique('reservation_at', 'reservations_reservation_at_unique');
        });
    }

    private function book(string $at): Reservation
    {
        return app(ReservationSchedule::class)->reserve([
            'reference' => 'LEGACY-'.bin2hex(random_bytes(5)),
            'type' => 'table',
            'table_size' => 2,
            'guests' => 2,
            'customer_name' => 'Legacy guest',
            'email' => 'legacy@example.com',
            'phone' => '09171234567',
            'reservation_at' => $at,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);
    }

    public function test_booking_works_during_a_code_only_deployment_before_migrations_run(): void
    {
        $first = $this->book('2030-01-02 18:00:00');
        $this->book('2030-01-02 18:30:00');
        $schedule = app(ReservationSchedule::class);

        $this->assertSame('06:00 PM – 08:00 PM', $first->time_range);
        $this->assertFalse($schedule->isAvailable('2030-01-02 18:00:00', 'table', 2));

        try {
            $this->book('2030-01-02 18:00:00');
            $this->fail('The legacy unique schedule should be reported before insert.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reservation_at', $exception->errors());
        }

        $this->travel(30)->minutes();
        $this->assertSame('expired', $first->booking_status);
        $this->assertSame(2, $schedule->expireHolds());
        $this->assertDatabaseCount('reservations', 2);
    }
}
