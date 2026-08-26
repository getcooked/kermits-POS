<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_only_their_own_reservations_and_orders(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $other = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        Reservation::query()->create(['user_id' => $customer->id, 'reference' => 'KRM-MINE', 'type' => 'table', 'table_size' => 4, 'customer_name' => $customer->name, 'email' => $customer->email, 'phone' => '09171234567', 'reservation_at' => now()->addDay(), 'guests' => 4, 'status' => 'confirmed']);
        Reservation::query()->create(['user_id' => $other->id, 'reference' => 'KRM-OTHER', 'type' => 'table', 'table_size' => 2, 'customer_name' => $other->name, 'email' => $other->email, 'phone' => '09171234567', 'reservation_at' => now()->addDay(), 'guests' => 2, 'status' => 'pending']);
        Order::query()->create(['user_id' => $customer->id, 'total' => 250, 'payment_method' => 'cash', 'payment_status' => 'pending']);
        Order::query()->create(['user_id' => $other->id, 'total' => 999, 'payment_method' => 'cash', 'payment_status' => 'pending']);

        $this->actingAs($customer)->get('/history')
            ->assertOk()
            ->assertSee('KRM-MINE')
            ->assertSee('250.00')
            ->assertDontSee('KRM-OTHER')
            ->assertDontSee('999.00');
    }

    public function test_staff_and_guests_cannot_open_customer_history(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->get('/history')->assertRedirect('/login');
        $this->actingAs($admin)->get('/history')->assertForbidden();
    }

    public function test_super_admin_status_change_is_recorded_in_customer_timeline(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $reservation = Reservation::query()->create(['user_id' => $customer->id, 'reference' => 'KRM-TIMELINE', 'type' => 'table', 'table_size' => 2, 'customer_name' => $customer->name, 'email' => $customer->email, 'phone' => '09171234567', 'reservation_at' => now()->addDay(), 'guests' => 2, 'status' => 'pending']);

        $this->actingAs($superAdmin)->patch('/reservations/'.$reservation->id.'/status', ['status' => 'confirmed'])->assertRedirect();

        $this->assertDatabaseHas('reservation_status_histories', ['reservation_id' => $reservation->id, 'from_status' => 'pending', 'to_status' => 'confirmed', 'changed_by' => $superAdmin->id]);
        $this->actingAs($customer)->get('/history')->assertOk()->assertSee('Confirmed')->assertSee('Updated by admin');
    }

    public function test_only_super_admin_can_view_a_selected_customer_history(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $other = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Reservation::query()->create(['user_id' => $customer->id, 'reference' => 'KRM-SELECTED', 'type' => 'table', 'table_size' => 4, 'customer_name' => $customer->name, 'email' => $customer->email, 'phone' => '09171234567', 'reservation_at' => now()->addDay(), 'guests' => 4, 'status' => 'pending']);
        Reservation::query()->create(['user_id' => $other->id, 'reference' => 'KRM-NOT-SELECTED', 'type' => 'table', 'table_size' => 2, 'customer_name' => $other->name, 'email' => $other->email, 'phone' => '09171234567', 'reservation_at' => now()->addDay(), 'guests' => 2, 'status' => 'pending']);

        $this->actingAs($superAdmin)->get('/customers/'.$customer->id.'/history')
            ->assertOk()
            ->assertSee('KRM-SELECTED')
            ->assertDontSee('KRM-NOT-SELECTED');

        $this->actingAs($admin)->get('/customers/'.$customer->id.'/history')->assertForbidden();
    }

    public function test_customer_and_cashier_cannot_view_staff_customer_history(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $this->actingAs($customer)->get('/customers/'.$customer->id.'/history')->assertForbidden();
        $this->actingAs($cashier)->get('/customers/'.$customer->id.'/history')->assertForbidden();
    }

    public function test_customer_can_view_and_print_only_their_reservation_receipt(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $other = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $reservation = Reservation::query()->create(['user_id' => $customer->id, 'reference' => 'KRM-RECEIPT', 'type' => 'table', 'table_size' => 4, 'customer_name' => $customer->name, 'email' => $customer->email, 'phone' => '09171234567', 'reservation_at' => now()->addDay(), 'guests' => 4, 'payment_method' => 'cash', 'status' => 'confirmed']);

        $this->actingAs($customer)->get('/history')
            ->assertOk()
            ->assertSee('View reservation')
            ->assertSee('Print receipt');

        $this->actingAs($customer)->get('/reservations/'.$reservation->id.'/receipt')
            ->assertOk()
            ->assertSee('RESERVATION RECEIPT')
            ->assertSee('KRM-RECEIPT');

        $this->actingAs($other)->get('/reservations/'.$reservation->id.'/receipt')->assertForbidden();
    }
}
