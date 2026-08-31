<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ReservationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_submit_a_reservation_request(): void
    {
        $customer = User::factory()->create([
            'name' => 'Booking Customer',
            'email' => 'customer@gmail.com',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $response = $this->actingAs($customer)->post('/book', [
            'type' => 'table',
            'table_size' => 4,
            'customer_name' => 'Booking Customer',
            'email' => 'customer@gmail.com',
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'guests' => 4,
            'food_request' => 'Four set meals',
            'notes' => 'Window table',
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reservations', [
            'user_id' => $customer->id,
            'email' => 'customer@gmail.com',
            'status' => 'pending',
        ]);
    }

    public function test_guest_cannot_open_or_submit_a_reservation(): void
    {
        $this->get('/book')->assertRedirect('/login');

        $this->post('/book', [
            'type' => 'table',
            'customer_name' => 'Guest',
            'email' => 'guest@gmail.com',
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'guests' => 2,
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_viewing_the_booking_form_does_not_consume_the_submission_rate_limit(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        for ($visit = 0; $visit < 11; $visit++) {
            $this->actingAs($customer)->get('/book')->assertOk();
        }

        $this->post('/book', [
            'type' => 'table',
            'table_size' => 2,
            'customer_name' => $customer->name,
            'email' => $customer->email,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'guests' => 2,
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_table_reservation_requires_an_allowed_table_size(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)->post('/book', [
            'type' => 'table',
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'guests' => 2,
        ])->assertSessionHasErrors('table_size');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_customer_can_choose_food_from_the_current_menu(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $meal = Product::query()->create(['name' => 'Family Meal', 'price' => 250, 'stock' => 10, 'active' => true]);

        $this->actingAs($customer)->post('/book', [
            'type' => 'table',
            'table_size' => 4,
            'customer_name' => $customer->name,
            'email' => $customer->email,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'menu_items' => [$meal->id => 2],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertDatabaseHas('reservation_items', [
            'product_id' => $meal->id,
            'quantity' => 2,
            'unit_price' => 250,
            'subtotal' => 500,
        ]);
    }

    public function test_food_request_is_optional(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)->post('/book', [
            'type' => 'table',
            'table_size' => 2,
            'customer_name' => $customer->name,
            'email' => $customer->email,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'menu_items' => [],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertDatabaseHas('reservations', ['user_id' => $customer->id, 'guests' => 2]);
        $this->assertDatabaseCount('reservation_items', 0);
    }

    public function test_each_food_request_item_is_limited_to_twenty_two(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $meal = Product::query()->create(['name' => 'Limited Meal', 'price' => 100, 'stock' => 100, 'active' => true]);

        $this->actingAs($customer)->post('/book', [
            'type' => 'table', 'table_size' => 4, 'customer_name' => $customer->name,
            'email' => $customer->email, 'phone' => '09171234567',
            'reservation_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'menu_items' => [$meal->id => 23], 'payment_method' => 'cash',
        ])->assertSessionHasErrors('menu_items.'.$meal->id);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_price_is_calculated_from_server_prices(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $meal = Product::query()->create(['name' => 'Priced Meal', 'price' => 200, 'stock' => 10, 'active' => true]);

        $this->actingAs($customer)->post('/book', [
            'type' => 'table',
            'table_size' => 4,
            'customer_name' => $customer->name,
            'email' => $customer->email,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'menu_items' => [$meal->id => 2],
            'payment_method' => 'gcash',
            'payment_reference' => '1234567890123',
            'payment_proof' => $this->fakePng('proof.png'),
            'total_amount' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('reservations', ['user_id' => $customer->id, 'reservation_fee' => 250, 'food_total' => 400, 'total_amount' => 650, 'payment_method' => 'gcash', 'payment_status' => 'pending']);
    }

    public function test_gcash_proof_is_private_and_visible_only_to_owner_and_super_admin(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $other = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($customer)->post('/book', [
            'type' => 'table', 'table_size' => 2, 'customer_name' => $customer->name,
            'email' => $customer->email, 'phone' => '09171234567',
            'reservation_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'payment_method' => 'gcash', 'payment_reference' => '1234567890123', 'payment_proof' => $this->fakePng('payment.png'),
        ])->assertRedirect();

        $reservation = Reservation::query()->whereBelongsTo($customer)->firstOrFail();
        Storage::disk('local')->assertExists($reservation->payment_proof_path);
        $this->actingAs($customer)->get('/reservations/'.$reservation->id.'/payment-proof')->assertOk();
        $this->actingAs($superAdmin)->get('/reservations/'.$reservation->id.'/payment-proof')->assertOk();
        $this->actingAs($admin)->get('/reservations/'.$reservation->id.'/payment-proof')->assertForbidden();
        $this->actingAs($other)->get('/reservations/'.$reservation->id.'/payment-proof')->assertForbidden();
    }

    public function test_phone_number_must_be_eleven_digits_and_start_with_zero_nine(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        foreach (['9171234567', '08171234567', '091712345678'] as $invalidPhone) {
            $this->actingAs($customer)->post('/book', [
                'type' => 'table',
                'table_size' => 2,
                'customer_name' => $customer->name,
                'email' => $customer->email,
                'phone' => $invalidPhone,
                'reservation_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])->assertSessionHasErrors('phone');
        }

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_confirmation_page_requires_a_valid_signature(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $reservation = Reservation::query()->create([
            'user_id' => $customer->id,
            'reference' => 'KRM-TEST',
            'type' => 'table',
            'customer_name' => 'Customer',
            'email' => 'customer@example.com',
            'phone' => '09171234567',
            'reservation_at' => now()->addDay(),
            'guests' => 2,
        ]);

        $this->actingAs($customer)
            ->get('/book/success/'.$reservation->reference)
            ->assertForbidden();
    }

    public function test_super_admin_can_confirm_a_reservation(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $reservation = Reservation::query()->create([
            'reference' => 'KRM-TEST',
            'type' => 'exclusive',
            'customer_name' => 'Customer',
            'email' => 'customer@example.com',
            'phone' => '09171234567',
            'reservation_at' => now()->addDay(),
            'guests' => 20,
        ]);

        $this->actingAs($superAdmin)->patch('/reservations/'.$reservation->id.'/status', [
            'status' => 'confirmed',
        ])->assertRedirect();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
            'handled_by' => $superAdmin->id,
        ]);
    }

    public function test_admin_cannot_access_or_update_reservations(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $reservation = Reservation::query()->create([
            'reference' => 'KRM-ADMIN-FORBIDDEN',
            'type' => 'table',
            'table_size' => 2,
            'customer_name' => 'Customer',
            'email' => 'customer@gmail.com',
            'phone' => '09171234567',
            'reservation_at' => now()->addDay(),
            'guests' => 2,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->get('/reservations')->assertForbidden();
        $this->actingAs($admin)
            ->patch('/reservations/'.$reservation->id.'/status', ['status' => 'confirmed'])
            ->assertForbidden();

        $this->assertSame('pending', $reservation->fresh()->status);
    }

    public function test_super_admin_can_see_table_and_food_request_details(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $meal = Product::query()->create(['name' => 'Kermit Special', 'price' => 175, 'stock' => 10, 'active' => true]);
        $reservation = Reservation::query()->create([
            'reference' => 'KRM-ADMIN-VIEW',
            'type' => 'table',
            'table_size' => 4,
            'customer_name' => 'Viewing Customer',
            'email' => 'viewer@gmail.com',
            'phone' => '09171234567',
            'reservation_at' => now()->addDay(),
            'guests' => 4,
            'food_request' => 'No peanuts',
            'status' => 'pending',
        ]);
        $reservation->items()->create(['product_id' => $meal->id, 'quantity' => 2, 'unit_price' => 175, 'subtotal' => 350]);

        $this->actingAs($superAdmin)->get('/reservations')
            ->assertOk()
            ->assertSee('4-seater table')
            ->assertSee('09171234567')
            ->assertSee('Kermit Special')
            ->assertSee('350.00')
            ->assertSee('No peanuts')
            ->assertSee('Approve reservation');
    }

    public function test_reservation_only_becomes_successful_after_super_admin_approval(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $reservation = Reservation::query()->create(['user_id' => $customer->id, 'reference' => 'KRM-APPROVAL', 'type' => 'table', 'customer_name' => $customer->name, 'email' => $customer->email, 'phone' => '09171234567', 'reservation_at' => now()->addDay(), 'guests' => 2, 'status' => 'pending']);
        $url = URL::temporarySignedRoute('reservations.success', now()->addMinutes(10), ['reference' => $reservation->reference]);

        $this->actingAs($customer)->get($url)->assertOk()->assertSee('AWAITING ADMIN APPROVAL')->assertDontSee('Reservation confirmed');
        $this->actingAs($superAdmin)->patch('/reservations/'.$reservation->id.'/status', ['status' => 'confirmed'])->assertRedirect();
        $this->actingAs($customer)->get($url)->assertOk()->assertSee('Reservation confirmed')->assertSee('ADMIN APPROVED');
    }

    public function test_super_admin_cannot_skip_pending_reservation_to_completed(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $reservation = Reservation::query()->create(['reference' => 'KRM-ORDERED', 'type' => 'table', 'customer_name' => 'Customer', 'email' => 'customer@gmail.com', 'phone' => '09171234567', 'reservation_at' => now()->addDay(), 'guests' => 2, 'status' => 'pending']);

        $this->actingAs($superAdmin)->patch('/reservations/'.$reservation->id.'/status', ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->assertSame('pending', $reservation->fresh()->status);
    }

    public function test_customer_cannot_submit_a_reservation_for_an_occupied_schedule(): void
    {
        $schedule = now()->addDays(2)->startOfHour();
        $firstCustomer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $secondCustomer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        Reservation::query()->create([
            'user_id' => $firstCustomer->id,
            'reference' => 'KRM-TAKEN-SLOT',
            'type' => 'table',
            'table_size' => 4,
            'customer_name' => $firstCustomer->name,
            'email' => $firstCustomer->email,
            'phone' => '09171234567',
            'reservation_at' => $schedule,
            'guests' => 4,
            'status' => 'confirmed',
        ]);

        $this->actingAs($secondCustomer)->post('/book', [
            'type' => 'table',
            'table_size' => 2,
            'customer_name' => $secondCustomer->name,
            'email' => $secondCustomer->email,
            'phone' => '09171234567',
            'reservation_at' => $schedule->format('Y-m-d H:i:s'),
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('reservation_at');

        $this->assertDatabaseCount('reservations', 1);
    }

    private function fakePng(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
