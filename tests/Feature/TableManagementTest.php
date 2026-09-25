<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ReservationPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_table_management_in_sidebar_with_current_tables(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Table Management')
            ->assertSee(route('tables.index'), false);

        $this->actingAs($superAdmin)->get(route('tables.index'))
            ->assertOk()
            ->assertSee('<h1>Table Management</h1>', false)
            ->assertSee('name="tables[0][guests]"', false)
            ->assertSee('value="150.00"', false);
    }

    public function test_only_super_admin_can_open_or_change_tables(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_CASHIER, User::ROLE_CUSTOMER] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('tables.index'))->assertForbidden();
            $this->actingAs($user)->put(route('tables.update'), [
                'tables' => [['guests' => 2, 'fee' => 1]],
            ])->assertForbidden();
        }

        $this->assertNull(SystemSetting::get(ReservationPricing::TABLES_SETTING_KEY));
    }

    public function test_super_admin_can_change_guest_numbers_and_prices_used_by_new_bookings(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($superAdmin)->put(route('tables.update'), [
            'tables' => [
                ['guests' => 6, 'fee' => '380.50'],
                ['guests' => 2, 'fee' => '175'],
                ['guests' => 10, 'fee' => '600'],
            ],
        ])->assertRedirect(route('tables.index'))->assertSessionHas('status', 'Table options updated successfully.');

        $this->assertSame([2 => 175.0, 6 => 380.5, 10 => 600.0], app(ReservationPricing::class)->tableFees());

        $this->actingAs($customer)->get(route('reservations.create'))
            ->assertOk()
            ->assertSee('value="6" data-fee="380.50"', false)
            ->assertDontSee('value="4" data-fee=', false);

        $this->actingAs($customer)->post(route('reservations.store'), $this->reservation($customer, 6))
            ->assertRedirect();

        $reservation = Reservation::query()->whereBelongsTo($customer)->firstOrFail();
        $this->assertSame(6, $reservation->table_size);
        $this->assertSame(380.5, (float) $reservation->reservation_fee);
    }

    public function test_removed_guest_numbers_can_no_longer_be_booked(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($superAdmin)->put(route('tables.update'), [
            'tables' => [['guests' => 2, 'fee' => 150], ['guests' => 6, 'fee' => 300]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($customer)->post(route('reservations.store'), $this->reservation($customer, 4))
            ->assertSessionHasErrors('table_size');

        $this->assertSame(0, Reservation::query()->count());
    }

    public function test_mobile_catalog_and_booking_use_the_managed_tables(): void
    {
        SystemSetting::query()->create([
            'key' => ReservationPricing::TABLES_SETTING_KEY,
            'value' => json_encode([['guests' => 3, 'fee' => '210.00'], ['guests' => 5, 'fee' => '320.00']]),
        ]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => 'MobilePassword123!']);
        $token = $this->postJson('/api/v1/login', [
            'login' => $customer->email, 'password' => 'MobilePassword123!', 'device_name' => 'Feature test',
        ])->assertOk()->json('data.token');
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/products', $headers)
            ->assertOk()
            ->assertJsonPath('data.table_fees', ['3' => 210, '5' => 320]);

        $booking = [
            'type' => 'table', 'phone' => '09171234567',
            'reservation_at' => now()->addDay()->setTime(12, 0)->toIso8601String(),
            'payment_method' => 'cash',
        ];

        $this->postJson('/api/v1/reservations', [...$booking, 'table_size' => 4], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('table_size');

        $this->postJson('/api/v1/reservations', [...$booking, 'table_size' => 5], $headers)
            ->assertCreated()
            ->assertJsonPath('data.table_size', 5)
            ->assertJsonPath('data.reservation_fee', 320);
    }

    public function test_tables_require_valid_unique_guest_numbers_and_prices(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $invalid = [
            'duplicate guest number' => [[['guests' => 4, 'fee' => 100], ['guests' => 4, 'fee' => 200]], 'tables.0.guests'],
            'more guests than the largest table' => [[['guests' => 13, 'fee' => 100]], 'tables.0.guests'],
            'zero guests' => [[['guests' => 0, 'fee' => 100]], 'tables.0.guests'],
            'fractional guests' => [[['guests' => 2.5, 'fee' => 100]], 'tables.0.guests'],
            'negative price' => [[['guests' => 2, 'fee' => -1]], 'tables.0.fee'],
            'three decimal price' => [[['guests' => 2, 'fee' => '1.005']], 'tables.0.fee'],
            'no tables' => [[], 'tables'],
        ];

        foreach ($invalid as $case => [$tables, $errorKey]) {
            $this->actingAs($superAdmin)
                ->from(route('tables.index'))
                ->put(route('tables.update'), ['tables' => $tables])
                ->assertSessionHasErrors($errorKey, "Expected an error for: {$case}");
        }

        $this->assertNull(SystemSetting::get(ReservationPricing::TABLES_SETTING_KEY));
    }

    public function test_existing_party_size_prices_carry_over_until_tables_are_saved(): void
    {
        SystemSetting::query()->create(['key' => 'reservation_table_fee_4', 'value' => '333.00']);

        $this->assertSame(
            [1 => 100.0, 2 => 150.0, 4 => 333.0, 8 => 450.0, 12 => 650.0],
            app(ReservationPricing::class)->tableFees(),
        );
    }

    private function reservation(User $customer, int $tableSize): array
    {
        return [
            'type' => 'table',
            'table_size' => $tableSize,
            'customer_name' => $customer->name,
            'email' => $customer->email,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->setTime(12, 0)->format('Y-m-d H:i:s'),
            'payment_method' => 'cash',
        ];
    }
}
