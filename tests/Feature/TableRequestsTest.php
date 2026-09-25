<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationSchedule;
use App\Services\TableLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TableRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2030, 1, 1)->setTime(9, 0));
    }

    public function test_a_requested_table_cannot_be_booked_twice_for_overlapping_times(): void
    {
        $tableFive = $this->table(5);
        $first = $this->book(guests: 4, tableId: $tableFive->id);
        $this->assertSame($tableFive->id, $first->dining_table_id);
        $this->assertSame('Table 5', $first->fresh()->table_label);

        $error = $this->bookingError(fn () => $this->book('2030-01-02 19:00:00', guests: 4, tableId: $tableFive->id));
        $this->assertSame(['dining_table_id'], array_keys($error));
        $this->assertStringContainsString('Table 5 is already booked', $error['dining_table_id'][0]);

        // Another 4-seat table is still free for a party that has no preference.
        $this->assertNull($this->book('2030-01-02 19:00:00', guests: 4)->dining_table_id);
        $this->book('2030-01-02 20:30:00', guests: 4, tableId: $tableFive->id);
        $this->assertSame(2, Reservation::query()->where('dining_table_id', $tableFive->id)->count());
    }

    public function test_a_table_too_small_for_the_party_cannot_be_requested(): void
    {
        $twoSeat = $this->table(1);

        $error = $this->bookingError(fn () => $this->book(guests: 4, tableId: $twoSeat->id));

        $this->assertArrayHasKey('dining_table_id', $error);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_parties_without_a_preference_move_aside_for_a_table_request(): void
    {
        $this->useTables([4, 4]);
        $firstTable = $this->table(1);

        $this->book(guests: 4);
        $requested = $this->book(guests: 4, tableId: $firstTable->id);
        $this->assertSame($firstTable->id, $requested->dining_table_id);

        $this->bookingError(fn () => $this->book(guests: 4));
    }

    public function test_a_requested_table_is_held_even_for_a_smaller_party(): void
    {
        $this->useTables([2, 4]);
        $this->book(guests: 2, tableId: $this->table(2)->id);

        $this->bookingError(fn () => $this->book(guests: 4));
        $this->assertNull($this->book(guests: 2)->dining_table_id);
    }

    public function test_a_customer_cannot_hold_two_reservations_at_the_same_time(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $other = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $this->book(userId: $customer->id);

        $error = $this->bookingError(fn () => $this->book('2030-01-02 19:00:00', userId: $customer->id));
        $this->assertStringContainsString('already have a reservation', $error['reservation_at'][0]);
        $this->bookingError(fn () => $this->book('2030-01-02 17:00:00', type: 'exclusive', userId: $customer->id));

        $this->book('2030-01-02 20:00:00', userId: $customer->id);
        $this->book(userId: $other->id);
        $this->assertSame(2, Reservation::query()->where('user_id', $customer->id)->count());
    }

    public function test_cancelled_reservations_do_not_block_the_same_customer(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $first = $this->book(userId: $customer->id);
        app(ReservationSchedule::class)->changeStatus($first, 'cancelled', $admin->id);

        $this->book(userId: $customer->id);

        $this->assertSame(2, Reservation::query()->where('user_id', $customer->id)->count());
    }

    public function test_cleanup_time_keeps_a_table_free_after_each_booking(): void
    {
        $this->useTables([2]);
        $this->assertSame(15, app(TableLayout::class)->turnoverMinutes());
        $this->book();

        $this->bookingError(fn () => $this->book('2030-01-02 20:00:00'));
        $this->bookingError(fn () => $this->book('2030-01-02 15:50:00'));
        $this->book('2030-01-02 20:15:00');
        $this->book('2030-01-02 15:45:00');

        app(TableLayout::class)->saveTurnoverMinutes(0);
        $this->book('2030-01-02 13:45:00');
        $this->assertDatabaseCount('reservations', 4);
    }

    public function test_availability_reports_how_many_tables_are_left(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $slotAt = fn (array $slots, string $time) => collect($slots)->firstWhere('start', '2030-01-02T'.$time);

        $slots = $this->actingAs($customer)->getJson('/book/availability?date=2030-01-02&type=table&guests=4')->assertOk()->json('data');
        $this->assertSame(5, $slotAt($slots, '18:00')['tables_left'], 'Four 4-seat tables and the 12-seat table fit four guests.');

        $this->book(guests: 4, tableId: $this->table(5)->id);
        $slots = $this->getJson('/book/availability?date=2030-01-02&type=table&guests=4')->json('data');
        $this->assertSame(4, $slotAt($slots, '18:00')['tables_left']);
        $this->assertSame(5, $slotAt($slots, '08:00')['tables_left']);

        $slots = $this->getJson('/book/availability?date=2030-01-02&type=table&guests=4&table='.$this->table(5)->id)->json('data');
        $this->assertFalse($slotAt($slots, '18:00')['available']);
        $this->assertFalse($slotAt($slots, '20:00')['available'], 'Cleanup time follows the booking.');
        $this->assertSame(1, $slotAt($slots, '20:30')['tables_left']);

        $slots = $this->getJson('/book/availability?date=2030-01-02&type=exclusive&guests=30')->json('data');
        $this->assertFalse($slotAt($slots, '18:00')['available'], 'An exclusive booking needs the whole venue free.');
        $this->assertNull($slotAt($slots, '08:00')['tables_left']);
    }

    public function test_customers_can_request_a_table_on_the_web_booking_page(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $tableEight = $this->table(8);

        $this->actingAs($customer)->get(route('reservations.create'))->assertOk()
            ->assertSee('Any available table (recommended)')
            ->assertSee('value="'.$tableEight->id.'" data-seats="12"', false);

        $this->post('/book', $this->webBooking($customer, ['dining_table_id' => $tableEight->id, 'table_size' => 8]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame($tableEight->id, Reservation::query()->sole()->dining_table_id);

        $other = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $this->actingAs($other)->post('/book', $this->webBooking($other, ['dining_table_id' => $tableEight->id, 'table_size' => 8]))
            ->assertSessionHasErrors('dining_table_id');
    }

    public function test_inactive_tables_cannot_be_requested_and_exclusive_bookings_ignore_the_field(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $tableFive = $this->table(5);
        $tableFive->update(['active' => false]);

        $this->actingAs($customer)->get(route('reservations.create'))->assertDontSee('value="'.$tableFive->id.'" data-seats', false);
        $this->post('/book', $this->webBooking($customer, ['dining_table_id' => $tableFive->id]))
            ->assertSessionHasErrors('dining_table_id');

        $this->post('/book', $this->webBooking($customer, ['type' => 'exclusive', 'guests' => 30, 'table_size' => null, 'dining_table_id' => $this->table(1)->id]))
            ->assertSessionHasNoErrors();
        $this->assertNull(Reservation::query()->sole()->dining_table_id);
    }

    public function test_shop_checkout_and_mobile_booking_accept_a_table_request(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => 'Password123!']);
        $product = Product::query()->create(['name' => 'Meal', 'price' => 100, 'stock' => 10, 'active' => true]);
        $tableTwo = $this->table(2);

        $this->actingAs($customer)->get('/shop')->assertOk()->assertSee('id="checkout_dining_table_id"', false);
        $this->post('/shop/orders', [
            'quantities' => [$product->id => 1], 'table_size' => 2, 'dining_table_id' => $tableTwo->id,
            'phone' => '09171234567', 'reservation_at' => '2030-01-02 12:00:00', 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();
        $this->assertSame($tableTwo->id, Reservation::query()->sole()->dining_table_id);

        $token = $this->postJson('/api/v1/login', ['login' => $customer->email, 'password' => 'Password123!'])->assertOk()->json('data.token');
        $this->withToken($token)->getJson('/api/v1/products')->assertOk()
            ->assertJsonPath('data.tables.1', ['id' => $tableTwo->id, 'number' => 2, 'seats' => 2]);

        $this->withToken($token)->postJson('/api/v1/reservations', [
            'type' => 'table', 'table_size' => 2, 'phone' => '09171234567',
            'reservation_at' => '2030-01-02T13:00:00+08:00', 'payment_method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('reservation_at');

        $this->withToken($token)->postJson('/api/v1/reservations', [
            'type' => 'table', 'table_size' => 2, 'dining_table_id' => $tableTwo->id, 'phone' => '09171234567',
            'reservation_at' => '2030-01-02T18:00:00+08:00', 'payment_method' => 'cash',
        ])->assertCreated()->assertJsonPath('data.table_label', 'Table 2');
    }

    public function test_super_admin_can_add_change_and_retire_tables_and_set_cleanup_time(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)->get(route('tables.index'))->assertOk()
            ->assertSee('Tables in the restaurant')
            ->assertSee('3 &times; 2-seat', false)
            ->assertSee('name="turnover_minutes"', false);

        $layout = $this->currentLayout();
        $layout[0]['seats'] = 3;
        $layout[1]['active'] = '0';
        $layout[] = ['number' => 9, 'seats' => 6, 'active' => '1'];

        $this->put(route('tables.layout.update'), ['dining_tables' => $layout, 'turnover_minutes' => 30])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tables.index'))
            ->assertSessionHas('status', 'Tables updated successfully.');

        $this->assertSame(3, $this->table(1)->seats);
        $this->assertFalse($this->table(2)->active);
        $this->assertSame(6, $this->table(9)->seats);
        $this->assertSame(30, app(TableLayout::class)->turnoverMinutes());
        $this->assertCount(8, app(TableLayout::class)->slots());
    }

    public function test_tables_can_swap_numbers_and_unused_tables_can_be_removed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $layout = $this->currentLayout();
        [$layout[0]['number'], $layout[1]['number']] = [$layout[1]['number'], $layout[0]['number']];
        $removedId = $layout[2]['id'];
        unset($layout[2]);

        $this->actingAs($admin)->put(route('tables.layout.update'), ['dining_tables' => array_values($layout), 'turnover_minutes' => 15])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, DiningTable::query()->find($layout[0]['id'])->number);
        $this->assertSame(1, DiningTable::query()->find($layout[1]['id'])->number);
        $this->assertNull(DiningTable::query()->find($removedId));
    }

    public function test_table_changes_that_would_strand_reservations_are_refused(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $tableFive = $this->table(5);
        $this->book(guests: 4, tableId: $tableFive->id);
        $this->book('2030-01-02 18:00:00', guests: 12);

        $cases = [
            'remove a table with reservations' => fn (array $layout) => array_values(array_filter($layout, fn ($table) => $table['number'] !== 5)),
            'stop booking a requested table' => fn (array $layout) => $this->changeTable($layout, 5, ['active' => '0']),
            'shrink a requested table' => fn (array $layout) => $this->changeTable($layout, 5, ['seats' => 2]),
            'leave no table for an upcoming large party' => fn (array $layout) => $this->changeTable($layout, 8, ['active' => '0']),
            'leave nothing bookable' => fn (array $layout) => array_map(fn ($table) => [...$table, 'active' => '0'], $layout),
        ];

        foreach ($cases as $case => $change) {
            $this->actingAs($admin)->from(route('tables.index'))
                ->put(route('tables.layout.update'), ['dining_tables' => $change($this->currentLayout()), 'turnover_minutes' => 15])
                ->assertSessionHasErrors('dining_tables', "Expected a refusal to: {$case}");
        }

        $this->assertTrue($this->table(5)->active);
        $this->assertSame(4, $this->table(5)->seats);
        $this->assertTrue($this->table(8)->active);
    }

    public function test_only_super_admin_can_change_tables(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_CASHIER, User::ROLE_CUSTOMER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->put(route('tables.layout.update'), ['dining_tables' => $this->currentLayout(), 'turnover_minutes' => 0])
                ->assertForbidden();
        }

        $this->assertSame(15, app(TableLayout::class)->turnoverMinutes());
    }

    private function book(
        string $at = '2030-01-02 18:00:00',
        int $guests = 2,
        string $type = 'table',
        ?int $tableId = null,
        ?int $userId = null,
    ): Reservation {
        return app(ReservationSchedule::class)->reserve([
            'reference' => 'TEST-'.bin2hex(random_bytes(6)), 'type' => $type,
            'customer_name' => 'Guest', 'email' => 'guest@example.com', 'phone' => '09171234567',
            'reservation_at' => $at, 'guests' => $guests, 'table_size' => $guests,
            'payment_method' => 'cash', 'payment_status' => 'pending',
            'dining_table_id' => $tableId, 'user_id' => $userId,
        ]);
    }

    private function bookingError(callable $book): array
    {
        try {
            $book();
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        $this->fail('Expected the booking to be refused.');
    }

    private function table(int $number): DiningTable
    {
        return DiningTable::query()->where('number', $number)->firstOrFail();
    }

    private function webBooking(User $customer, array $overrides = []): array
    {
        return [
            'type' => 'table', 'table_size' => 4, 'customer_name' => $customer->name, 'email' => $customer->email,
            'phone' => '09171234567', 'reservation_at' => '2030-01-02 18:00:00', 'payment_method' => 'cash',
            ...$overrides,
        ];
    }

    private function currentLayout(): array
    {
        return DiningTable::query()->orderBy('number')->get()
            ->map(fn (DiningTable $table): array => ['id' => $table->id, 'number' => $table->number, 'seats' => $table->seats, 'active' => $table->active ? '1' : '0'])
            ->all();
    }

    private function changeTable(array $layout, int $number, array $changes): array
    {
        return array_map(fn (array $table): array => $table['number'] === $number ? [...$table, ...$changes] : $table, $layout);
    }
}
