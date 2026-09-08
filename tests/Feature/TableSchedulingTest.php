<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TableSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2030, 1, 1)->setTime(9, 0));
    }

    private function book(string $at = '2030-01-02 18:00:00', int $guests = 2, string $type = 'table'): Reservation
    {
        return app(ReservationSchedule::class)->reserve([
            'reference' => 'TEST-'.bin2hex(random_bytes(6)), 'type' => $type,
            'customer_name' => 'Guest', 'email' => 'guest@example.com', 'phone' => '09171234567',
            'reservation_at' => $at, 'guests' => $guests, 'table_size' => $guests,
            'payment_method' => 'cash', 'payment_status' => 'pending',
        ]);
    }

    public function test_configured_tables_match_the_restaurant(): void
    {
        $this->assertSame([1 => 2, 2 => 2, 3 => 2, 4 => 4, 5 => 4, 6 => 4, 7 => 4, 8 => 12], DiningTable::query()->pluck('capacity', 'number')->all());
    }

    public function test_simultaneous_and_overlapping_bookings_use_different_tables(): void
    {
        $first = $this->book();
        $second = $this->book();
        $overlap = $this->book('2030-01-02 18:30:00');
        $this->assertSame([1, 2, 3], [$first->diningTable->number, $second->diningTable->number, $overlap->diningTable->number]);
        $this->assertSame('20:00', $first->reservation_end_at->format('H:i'));
        $this->assertSame('09:30', $first->hold_expires_at->format('H:i'));
    }

    public function test_back_to_back_bookings_reuse_the_same_table(): void
    {
        $first = $this->book();
        $next = $this->book('2030-01-02 20:00:00');
        $this->assertSame($first->dining_table_id, $next->dining_table_id);
    }

    public function test_larger_parties_use_a_suitable_table_and_capacity_is_finite(): void
    {
        $this->assertSame(4, $this->book(guests: 4)->diningTable->number);
        $this->assertSame(8, $this->book(guests: 8)->diningTable->number);
        $this->expectException(ValidationException::class);
        $this->book(guests: 12);
    }

    public function test_all_eight_tables_can_be_booked_but_a_ninth_cannot(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->assertSame($i, $this->book()->diningTable->number);
        }
        $this->expectException(ValidationException::class);
        $this->book();
    }

    public function test_exclusive_booking_blocks_tables_and_other_exclusive_bookings(): void
    {
        $this->book(type: 'exclusive');
        $service = app(ReservationSchedule::class);
        $this->assertFalse($service->isAvailable('2030-01-02 19:00:00', 'table', 2));
        $this->assertFalse($service->isAvailable('2030-01-02 19:00:00', 'exclusive', 20));
        $this->assertTrue($service->isAvailable('2030-01-02 20:00:00', 'exclusive', 20));
        $this->expectException(ValidationException::class);
        $this->book();
    }

    public function test_existing_table_booking_blocks_an_overlapping_exclusive_booking(): void
    {
        $this->book();
        $this->expectException(ValidationException::class);
        $this->book('2030-01-02 17:00:00', type: 'exclusive');
    }

    public static function validTimes(): array
    {
        return [['08:00:00', '10:00'], ['21:30:00', '23:00'], ['22:00:00', '23:00'], ['2030-01-02T00:00:00Z', '10:00']];
    }

    #[DataProvider('validTimes')]
    public function test_opening_and_last_reservation_periods(string $time, string $end): void
    {
        $reservation = $this->book(str_contains($time, 'T') ? $time : '2030-01-02 '.$time);
        $this->assertSame($end, $reservation->reservation_end_at->format('H:i'));
    }

    public static function invalidTimes(): array
    {
        return [['07:59:59'], ['22:00:01'], ['22:30:00'], ['23:00:00'], ['00:00:00']];
    }

    #[DataProvider('invalidTimes')]
    public function test_bookings_outside_hours_are_rejected(string $time): void
    {
        $this->expectException(ValidationException::class);
        $this->book('2030-01-02 '.$time);
    }

    public function test_expired_holds_release_capacity_without_waiting_for_the_scheduler(): void
    {
        $first = $this->book();
        $this->travel(30)->minutes();
        $this->assertSame('expired', $first->booking_status);
        $next = $this->book();
        $this->assertSame($first->dining_table_id, $next->dining_table_id);
        $this->artisan('reservations:expire')->assertSuccessful();
        $this->assertSame('expired', $first->fresh()->status);
        $this->assertDatabaseHas('reservation_status_histories', ['reservation_id' => $first->id, 'to_status' => 'expired']);
    }

    public function test_confirmation_preserves_the_table_after_the_hold_deadline(): void
    {
        $first = $this->book();
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        app(ReservationSchedule::class)->changeStatus($first, 'confirmed', $admin->id);
        $this->travel(31)->minutes();
        $this->assertNull($first->hold_expires_at);
        $this->assertNotSame($first->dining_table_id, $this->book()->dining_table_id);
    }

    public function test_expired_reservation_cannot_be_approved_using_a_stale_page(): void
    {
        $first = $this->book();
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->travel(31)->minutes();
        $this->expectException(ValidationException::class);
        app(ReservationSchedule::class)->changeStatus($first, 'confirmed', $admin->id);
    }

    public function test_reassignment_checks_overlap_and_capacity_and_admin_permissions(): void
    {
        $first = $this->book();
        $second = $this->book();
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $this->actingAs($customer)->patch('/reservations/'.$first->id.'/table', ['dining_table_id' => 3])->assertForbidden();
        $this->actingAs($admin)->patch('/reservations/'.$first->id.'/table', ['dining_table_id' => $second->dining_table_id])->assertSessionHasErrors('dining_table_id');
        $this->actingAs($admin)->patch('/reservations/'.$first->id.'/table', ['dining_table_id' => 3])->assertSessionHasNoErrors();
        $this->assertSame(3, $first->fresh()->dining_table_id);
        $large = $this->book(guests: 12);
        $this->actingAs($admin)->patch('/reservations/'.$large->id.'/table', ['dining_table_id' => 4])->assertSessionHasErrors('dining_table_id');
    }

    public function test_tables_can_be_managed_but_active_assignments_are_protected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($admin)->get('/tables')->assertOk()->assertSee('Table 8');
        $this->post('/tables', ['number' => 9, 'capacity' => 6])->assertSessionHasNoErrors();
        $first = $this->book();
        $this->put('/tables/'.$first->dining_table_id, ['number' => 1, 'capacity' => 2, 'active' => 0])->assertSessionHasErrors('table');
        $this->put('/tables/2', ['number' => 2, 'capacity' => 2, 'active' => 0])->assertSessionHasNoErrors();
        $this->assertSame(3, $this->book()->diningTable->number);
    }

    public function test_cancellation_and_rejection_release_tables(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        foreach (['cancelled', 'rejected'] as $status) {
            $reservation = $this->book();
            app(ReservationSchedule::class)->changeStatus($reservation, $status, $admin->id);
            $this->assertTrue(app(ReservationSchedule::class)->isAvailable('2030-01-02 18:00:00', 'exclusive', 20));
        }
    }

    public function test_availability_lists_last_slot_and_hides_customer_information(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $this->book(type: 'exclusive');
        $slots = $this->actingAs($customer)->getJson('/book/availability?date=2030-01-02&type=table&guests=2')->assertOk()->json('data');
        $this->assertSame('2030-01-02T22:00', end($slots)['start']);
        $this->assertSame('2030-01-02T23:00', end($slots)['end']);
        $this->assertFalse(collect($slots)->firstWhere('start', '2030-01-02T18:00')['available']);
        $this->assertArrayNotHasKey('customer_name', $slots[0]);
    }

    public function test_web_and_mobile_reservations_and_checkout_enforce_hours(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => 'Password123!']);
        $product = Product::query()->create(['name' => 'Meal', 'price' => 100, 'stock' => 10, 'active' => true]);
        $payload = ['type' => 'table', 'table_size' => 2, 'phone' => '09171234567', 'reservation_at' => '2030-01-02 22:30:00', 'payment_method' => 'cash', 'customer_name' => $customer->name, 'email' => $customer->email];
        $this->actingAs($customer)->post('/book', $payload)->assertSessionHasErrors('reservation_at');
        $this->post('/shop/orders', $payload + ['quantities' => [$product->id => 1]])->assertSessionHasErrors('reservation_at');
        $token = $this->postJson('/api/v1/login', ['login' => $customer->email, 'password' => 'Password123!'])->assertOk()->json('data.token');
        $this->withToken($token)->postJson('/api/v1/reservations', $payload)->assertUnprocessable()->assertJsonValidationErrors('reservation_at');
        $this->postJson('/api/v1/orders', $payload + ['items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('reservation_at');
        $this->assertDatabaseCount('reservations', 0);
        $this->assertSame(10, $product->fresh()->stock);
        $payload['reservation_at'] = '2030-01-02T14:00:00Z';
        $this->postJson('/api/v1/reservations', $payload)->assertCreated()->assertJsonPath('data.table_number', 1)->assertJsonPath('data.reservation_end_at', '2030-01-02T23:00:00+08:00');
    }

    public function test_verified_payment_preserves_pending_hold_but_expired_payment_is_rejected(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create(['name' => 'Meal', 'price' => 100, 'stock' => 10, 'active' => true]);
        foreach ([false, true] as $expire) {
            $this->actingAs($customer)->post('/shop/orders', [
                'quantities' => [$product->id => 1], 'table_size' => 2, 'phone' => '09171234567',
                'reservation_at' => '2030-01-02 18:00:00', 'payment_method' => 'cash',
            ])->assertSessionHasNoErrors();
            $reservation = Reservation::query()->latest('id')->firstOrFail();
            if ($expire) {
                $this->travel(30)->minutes();
            }
            $response = $this->actingAs($cashier)->patch('/cashier/orders/'.$reservation->order_id.'/confirm-payment', ['cash_received' => 1000]);
            if ($expire) {
                $response->assertSessionHasErrors('reservation');
                $this->assertSame('pending', $reservation->fresh()->payment_status);
            } else {
                $response->assertSessionHasNoErrors();
                $this->assertSame('paid', $reservation->fresh()->payment_status);
                $this->assertNull($reservation->fresh()->hold_expires_at);
            }
        }
    }

    public function test_unassigned_legacy_booking_is_protected_until_staff_assigns_it(): void
    {
        $legacy = $this->book();
        $legacy->update(['dining_table_id' => null, 'hold_expires_at' => null]);
        $schedules = app(ReservationSchedule::class);
        $this->assertFalse($schedules->isAvailable('2030-01-02 19:00:00', 'table', 2));
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $schedules->reassign($legacy, 1, $admin->id);
        $this->assertTrue($schedules->isAvailable('2030-01-02 19:00:00', 'table', 2));
    }
}
