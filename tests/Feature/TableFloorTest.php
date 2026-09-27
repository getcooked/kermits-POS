<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationSchedule;
use App\Services\TableLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableFloorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2030, 1, 2)->setTime(9, 0));
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    public function test_a_party_that_leaves_early_frees_its_table_after_the_cleanup_time(): void
    {
        $tableFive = $this->table(5);
        $reservation = $this->confirmed('2030-01-02 12:00:00', guests: 4, tableId: $tableFive->id);

        $this->travelTo(now()->setTime(12, 0));
        $this->actingAs($this->admin)->post(route('tables.seat', $tableFive), ['reservation_id' => $reservation->id])
            ->assertRedirect(route('tables.index'))->assertSessionHas('status', "Table 5 marked occupied ({$reservation->reference}).");
        $this->assertNotNull($reservation->fresh()->seated_at);
        $this->assertTrue($tableFive->fresh()->isOccupied());

        // Still seated, so the table is held for the estimated stay.
        $this->assertFalse($this->schedule()->isAvailable('2030-01-02 13:00:00', 'table', 4, $tableFive->id));

        $this->travelTo(now()->setTime(12, 30));
        $this->actingAs($this->admin)->post(route('tables.free', $tableFive))
            ->assertRedirect(route('tables.index'))->assertSessionHas('status', 'Table 5 marked free.');

        $reservation->refresh();
        $this->assertSame('completed', $reservation->status);
        $this->assertSame('2030-01-02 12:30', $reservation->reservation_end_at->format('Y-m-d H:i'));
        $this->assertSame('completed', $reservation->statusHistories->last()->to_status);
        $this->assertFalse($tableFive->fresh()->isOccupied());

        // 15 minutes of cleanup, then the table can be booked again.
        $this->assertFalse($this->schedule()->isAvailable('2030-01-02 12:40:00', 'table', 4, $tableFive->id));
        $this->assertTrue($this->schedule()->isAvailable('2030-01-02 12:45:00', 'table', 4, $tableFive->id));
    }

    public function test_walk_ins_hold_a_table_until_it_is_marked_free(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $tableOne = $this->table(1);

        $this->actingAs($this->admin)->post(route('tables.seat', $tableOne))
            ->assertSessionHas('status', 'Table 1 marked occupied (Walk-in party).');
        $this->assertNull($tableOne->fresh()->occupied_reservation_id);
        $this->assertFalse($this->schedule()->isAvailable('2030-01-02 12:30:00', 'table', 2, $tableOne->id));

        $this->actingAs($this->admin)->post(route('tables.free', $tableOne))->assertSessionHasNoErrors();
        $this->assertTrue($this->schedule()->isAvailable('2030-01-02 12:30:00', 'table', 2, $tableOne->id));
    }

    public function test_a_party_staying_past_the_estimate_keeps_the_table_until_freed(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $tableOne = $this->table(1);
        $this->actingAs($this->admin)->post(route('tables.seat', $tableOne));

        $this->travelTo(now()->setTime(12, 20));
        $this->assertFalse($this->schedule()->isAvailable('2030-01-02 12:30:00', 'table', 2, $tableOne->id));
        $this->assertTrue($this->schedule()->isAvailable('2030-01-02 12:40:00', 'table', 2, $tableOne->id));

        $this->actingAs($this->admin)->get(route('tables.index'))
            ->assertOk()
            ->assertSee("Today's tables", false)
            ->assertSee('Staying longer than usual.');
    }

    public function test_an_occupied_requested_table_does_not_block_other_bookings(): void
    {
        $tableFive = $this->table(5);
        $requested = $this->confirmed('2030-01-02 12:30:00', guests: 4, tableId: $tableFive->id);

        $this->travelTo(now()->setTime(12, 0));
        $this->actingAs($this->admin)->post(route('tables.seat', $tableFive));

        // The requested booking can be seated elsewhere, so parties without a preference still fit.
        $this->assertTrue($this->schedule()->isAvailable('2030-01-02 12:30:00', 'table', 4));
        $this->book('2030-01-02 12:30:00', guests: 4);

        $this->actingAs($this->admin)->get(route('tables.index'))
            ->assertOk()
            ->assertSee("Next booking for this table: {$requested->reference} at 12:30 PM", false);
    }

    public function test_seating_is_refused_for_busy_or_too_small_tables_and_unapproved_bookings(): void
    {
        $large = $this->confirmed('2030-01-02 12:00:00', guests: 4);
        $pending = $this->book('2030-01-02 12:00:00', guests: 2);
        $this->travelTo(now()->setTime(12, 0));

        $this->actingAs($this->admin)->post(route('tables.seat', $this->table(1)), ['reservation_id' => $large->id])
            ->assertSessionHasErrors(['floor' => "Table 1 seats 2, but {$large->reference} is for 4 guests. Choose a bigger table."]);
        $this->actingAs($this->admin)->post(route('tables.seat', $this->table(1)), ['reservation_id' => $pending->id])
            ->assertSessionHasErrors('floor');

        $this->actingAs($this->admin)->post(route('tables.seat', $this->table(5)), ['reservation_id' => $large->id])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('tables.seat', $this->table(5)))
            ->assertSessionHasErrors(['floor' => 'Table 5 is already occupied. Mark it free first.']);
        $this->actingAs($this->admin)->post(route('tables.free', $this->table(1)))
            ->assertSessionHasErrors(['floor' => 'Table 1 is already free.']);
    }

    public function test_customers_only_see_arrival_times_and_staff_set_the_estimated_stay(): void
    {
        $slots = $this->schedule()->availabilityForDate('2030-01-02');
        $this->assertSame('6:00 PM', collect($slots)->firstWhere('start', '2030-01-02T18:00')['label']);

        $reservation = $this->book('2030-01-02 18:00:00');
        $this->assertSame('06:00 PM', $reservation->arrival_time);
        $this->assertSame('20:00', $reservation->reservation_end_at->format('H:i'));

        $layout = DiningTable::query()->orderBy('number')->get()
            ->map(fn (DiningTable $table): array => ['id' => $table->id, 'number' => $table->number, 'seats' => $table->seats, 'active' => '1'])
            ->all();
        $this->actingAs($this->admin)->put(route('tables.layout.update'), ['dining_tables' => $layout, 'turnover_minutes' => 15, 'stay_minutes' => 90])
            ->assertSessionHasNoErrors();

        $this->assertSame(90, app(TableLayout::class)->stayMinutes());
        $this->assertSame('19:30', $this->book('2030-01-02 18:00:00')->reservation_end_at->format('H:i'));
        $this->assertSame('20:00', $reservation->fresh()->reservation_end_at->format('H:i'));
    }

    public function test_cashiers_can_mark_tables_occupied_or_free_but_not_change_settings(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $reservation = $this->confirmed('2030-01-02 12:00:00', guests: 4);
        $table = $this->table(5);
        $this->travelTo(now()->setTime(12, 0));

        $this->actingAs($cashier)->get(route('cashier'))->assertOk()->assertSee(route('floor.index'), false);
        $this->actingAs($cashier)->get(route('floor.index'))
            ->assertOk()
            ->assertSee('<h1>Tables</h1>', false)
            ->assertSee('Mark occupied')
            ->assertSee($reservation->reference)
            ->assertDontSee('Cancel no-shows from Reservations')
            ->assertDontSee('name="stay_minutes"', false)
            ->assertDontSee(route('tables.layout.update'), false);

        $this->actingAs($cashier)->post(route('tables.seat', $table), ['reservation_id' => $reservation->id])
            ->assertRedirect(route('floor.index'))->assertSessionHas('status', "Table 5 marked occupied ({$reservation->reference}).");
        $this->actingAs($cashier)->get(route('floor.index'))->assertSee('Mark free');
        $this->actingAs($cashier)->post(route('tables.free', $table))
            ->assertRedirect(route('floor.index'))->assertSessionHas('status', 'Table 5 marked free.');
        $this->assertSame('completed', $reservation->fresh()->status);

        $this->actingAs($cashier)->get(route('tables.index'))->assertForbidden();
        $this->actingAs($cashier)->put(route('tables.layout.update'), ['dining_tables' => [], 'turnover_minutes' => 0, 'stay_minutes' => 60])->assertForbidden();
    }

    public function test_only_super_admin_and_cashiers_can_seat_or_free_tables(): void
    {
        $table = $this->table(1);
        foreach ([User::ROLE_ADMIN, User::ROLE_CUSTOMER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('floor.index'))->assertForbidden();
            $this->actingAs($user)->post(route('tables.seat', $table))->assertForbidden();
            $this->actingAs($user)->post(route('tables.free', $table))->assertForbidden();
        }

        $this->assertFalse($table->fresh()->isOccupied());
    }

    private function schedule(): ReservationSchedule
    {
        return app(ReservationSchedule::class);
    }

    private function confirmed(string $at, int $guests = 2, ?int $tableId = null): Reservation
    {
        $reservation = $this->book($at, $guests, $tableId);
        $this->schedule()->changeStatus($reservation, 'confirmed', $this->admin->id);

        return $reservation->fresh();
    }

    private function book(string $at, int $guests = 2, ?int $tableId = null): Reservation
    {
        return $this->schedule()->reserve([
            'reference' => 'TEST-'.bin2hex(random_bytes(6)), 'type' => 'table',
            'customer_name' => 'Guest', 'email' => 'guest@example.com', 'phone' => '09171234567',
            'reservation_at' => $at, 'guests' => $guests, 'table_size' => $guests,
            'payment_method' => 'cash', 'payment_status' => 'pending',
            'dining_table_id' => $tableId,
        ]);
    }

    private function table(int $number): DiningTable
    {
        return DiningTable::query()->where('number', $number)->firstOrFail();
    }
}
