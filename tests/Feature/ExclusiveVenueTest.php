<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationPricing;
use App\Services\ReservationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExclusiveVenueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2030, 1, 1)->setTime(9, 0));
        config()->set('services.paymongo.enabled', true);
        config()->set('services.paymongo.secret_key', 'sk_test_example');
        config()->set('services.paymongo.webhook_secret', 'whsec_example');
        config()->set('services.paymongo.payment_methods', ['gcash', 'qrph']);
    }

    public function test_the_exclusive_venue_takes_the_whole_day(): void
    {
        $event = $this->book('2030-01-02 15:00:00');

        $this->assertSame('2030-01-02 08:00', $event->reservation_at->format('Y-m-d H:i'));
        $this->assertSame('2030-01-02 23:00', $event->reservation_end_at->format('Y-m-d H:i'));
        $this->assertSame('Whole day', $event->arrival_time);
        $this->assertSame('Exclusive Venue', $event->type_label);

        $schedules = app(ReservationSchedule::class);
        $this->assertFalse($schedules->isAvailable('2030-01-02 08:00:00', 'table', 2));
        $this->assertFalse($schedules->isAvailable('2030-01-02 22:00:00', 'table', 2));
        $this->assertFalse($schedules->isAvailable('2030-01-02', 'exclusive', 30));
        $this->assertTrue($schedules->isAvailable('2030-01-03 08:00:00', 'table', 2));
    }

    public function test_the_venue_is_booked_a_day_ahead_on_a_day_without_table_bookings(): void
    {
        $this->assertArrayHasKey('reservation_at', $this->bookingError(fn () => $this->book('2030-01-01 18:00:00')));

        $this->book('2030-01-02 21:00:00', type: 'table');
        $this->assertArrayHasKey('reservation_at', $this->bookingError(fn () => $this->book('2030-01-02')));

        $this->assertSame('08:00', $this->book('2030-01-03')->reservation_at->format('H:i'));
    }

    public function test_walk_in_pay_is_refused_and_a_gcash_downpayment_is_verified_on_approval(): void
    {
        Storage::fake('local');
        app(ReservationPricing::class)->saveExclusiveFee(4999.99);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)->post('/book', $this->webBooking($customer, ['payment_method' => 'cash']))
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('reservations', 0);

        $this->post('/book', $this->webBooking($customer, [
            'payment_method' => 'gcash', 'payment_reference' => '1234567890123', 'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ]))->assertSessionHasNoErrors();
        $reservation = Reservation::query()->sole();
        $this->assertSame('4999.99', $reservation->total_amount);
        $this->assertSame('2500.00', $reservation->downpayment_amount, 'Half the total, rounded up to the centavo.');

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($admin)->patch(route('reservations.status', $reservation), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $reservation->refresh();
        $this->assertSame('confirmed', $reservation->status);
        $this->assertSame('partial', $reservation->payment_status);
        $this->assertSame(2499.99, $reservation->balance_due);

        $this->patch(route('reservations.status', $reservation), ['status' => 'completed'])->assertSessionHasNoErrors();
        $reservation->refresh();
        $this->assertSame('paid', $reservation->payment_status);
        $this->assertSame(0.0, $reservation->balance_due);
    }

    public function test_paymongo_charges_the_downpayment_and_approval_waits_for_it(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::response([
            'data' => ['id' => 'cs_test_123', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/test-session']],
        ])]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)->post('/book', $this->webBooking($customer, ['payment_method' => 'paymongo']))
            ->assertRedirect('https://checkout.paymongo.com/test-session');
        $reservation = Reservation::query()->with('order')->sole();
        Http::assertSent(fn ($request) => $request['data']['attributes']['line_items'] === [
            ['name' => 'Exclusive Venue downpayment', 'amount' => 250000, 'currency' => 'PHP', 'quantity' => 1],
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($admin)->patch(route('reservations.status', $reservation), ['status' => 'confirmed'])
            ->assertSessionHasErrors('status');
        $this->assertSame('pending', $reservation->fresh()->status);

        $this->sendWebhook($this->paidEvent($reservation->order, 500000))->assertStatus(422);
        $this->sendWebhook($this->paidEvent($reservation->order, 250000))->assertOk();
        $reservation->refresh();
        $this->assertSame('partial', $reservation->payment_status);
        $this->assertSame('2500.00', $reservation->amount_paid);
        $this->assertNull($reservation->hold_expires_at, 'A paid downpayment holds the day until staff decide.');

        $this->patch(route('reservations.status', $reservation), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    public function test_the_app_can_pay_in_full_but_not_at_the_counter(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'phone' => '09171234567', 'password' => 'MobilePassword123!']);
        $token = $this->postJson('/api/v1/login', [
            'login' => $customer->email, 'password' => 'MobilePassword123!', 'device_name' => 'Feature test',
        ])->assertOk()->json('data.token');
        $booking = ['type' => 'exclusive', 'guests' => 40, 'phone' => '09171234567', 'reservation_at' => '2030-01-02T08:00:00+08:00'];

        $this->withToken($token)->postJson('/api/v1/reservations', [...$booking, 'payment_method' => 'cash'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $this->withToken($token)->post('/api/v1/reservations', [
            ...$booking, 'payment_method' => 'gcash', 'payment_plan' => 'full',
            'payment_reference' => '1234567890123', 'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type_label', 'Exclusive Venue')
            ->assertJsonPath('data.arrival_time', 'Whole day')
            ->assertJsonPath('data.downpayment_amount', 5000);
    }

    public function test_walk_ins_are_not_seated_on_an_exclusive_venue_day(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $event = $this->book('2030-01-02', ['payment_method' => 'gcash', 'payment_reference' => '1234567890123', 'payment_proof_path' => 'payment-proofs/proof.jpg']);
        app(ReservationSchedule::class)->changeStatus($event, 'confirmed', $admin->id);
        $this->travelTo(now()->setDate(2030, 1, 2)->setTime(10, 0));
        $table = DiningTable::query()->orderBy('number')->firstOrFail();

        $this->actingAs($admin)->post(route('tables.seat', $table))->assertSessionHasErrors('floor');
        $this->assertNull($table->fresh()->occupied_at);
        $this->get(route('tables.index'))->assertOk()->assertSee('Exclusive Venue today');
    }

    public function test_super_admin_sets_the_exclusive_venue_price(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)->put(route('tables.exclusive.update'), ['exclusive_fee' => '0'])->assertSessionHasErrors('exclusive_fee');
        $this->put(route('tables.exclusive.update'), ['exclusive_fee' => '12500.50'])->assertRedirect(route('tables.index'));
        $this->assertSame(12500.5, app(ReservationPricing::class)->exclusiveFee());
        $this->get(route('tables.index'))->assertSee('12500.50');

        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $this->actingAs($customer)->get(route('reservations.create'))->assertSee('Exclusive Venue · whole day · ₱12,501');
    }

    private function book(string $at, array $attributes = [], string $type = 'exclusive'): Reservation
    {
        return app(ReservationSchedule::class)->reserve([
            'reference' => 'TEST-'.bin2hex(random_bytes(6)), 'type' => $type,
            'customer_name' => 'Guest', 'email' => 'guest@example.com', 'phone' => '09171234567',
            'reservation_at' => $at, 'guests' => $type === 'table' ? 2 : 30, 'table_size' => $type === 'table' ? 2 : null,
            'total_amount' => 5000, 'downpayment_amount' => 2500,
            'payment_method' => 'paymongo', 'payment_status' => 'pending',
            ...$attributes,
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

    private function webBooking(User $customer, array $overrides = []): array
    {
        return [
            'type' => 'exclusive', 'guests' => 40, 'customer_name' => $customer->name, 'email' => $customer->email,
            'phone' => '09171234567', 'reservation_at' => '2030-01-02T08:00',
            ...$overrides,
        ];
    }

    private function paidEvent(Order $order, int $amount): array
    {
        return ['data' => [
            'type' => 'checkout_session.payment.paid',
            'livemode' => false,
            'data' => [
                'id' => $order->fresh()->paymongo_checkout_id,
                'attributes' => [
                    'reference_number' => $order->reservation->reference,
                    'payments' => [[
                        'id' => 'pay_test_123',
                        'attributes' => ['status' => 'paid', 'amount' => $amount, 'currency' => 'PHP'],
                    ]],
                ],
            ],
        ]];
    }

    private function sendWebhook(array $event): TestResponse
    {
        $body = json_encode($event);
        $timestamp = (string) time();

        return $this->call('POST', '/api/paymongo/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Paymongo_Signature' => 't='.$timestamp.',te='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_example').',li=',
        ], $body);
    }
}
