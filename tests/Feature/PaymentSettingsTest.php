<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_upload_and_replace_gcash_qr_image(): void
    {
        Storage::fake('public');
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)->put('/settings/payment', [
            'gcash_qr' => $this->fakePng('gcash.png'),
        ])->assertRedirect();

        $firstPath = SystemSetting::get('gcash_qr_path');
        Storage::disk('public')->assertExists($firstPath);

        $this->actingAs($superAdmin)->put('/settings/payment', [
            'gcash_qr' => $this->fakePng('replacement.png'),
        ])->assertRedirect();

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists(SystemSetting::get('gcash_qr_path'));
    }

    public function test_only_super_admin_can_manage_payment_settings(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->get('/settings/payment')->assertRedirect('/login');
        $this->actingAs($admin)->get('/settings/payment')->assertForbidden();
        $this->actingAs($customer)->get('/settings/payment')->assertForbidden();
    }

    public function test_checkout_embeds_the_current_uploaded_gcash_qr(): void
    {
        Storage::fake('public');
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $upload = $this->fakePng('gcash.png');
        $expectedSource = 'data:image/png;base64,'.base64_encode(file_get_contents($upload->getPathname()));

        $this->actingAs($superAdmin)->put('/settings/payment', ['gcash_qr' => $upload])->assertRedirect();

        $this->actingAs($customer)->get('/shop')
            ->assertOk()
            ->assertSee('src="'.$expectedSource.'"', false)
            ->assertSee('Scan using your GCash app.');
    }

    public function test_checkout_handles_a_missing_uploaded_qr(): void
    {
        Storage::fake('public');
        SystemSetting::query()->create(['key' => 'gcash_qr_path', 'value' => 'payment/missing.png']);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)->get('/shop')
            ->assertOk()
            ->assertSee('gcash-qr-placeholder.svg')
            ->assertSee('The GCash QR is unavailable.')
            ->assertDontSee('Scan using your GCash app.');
    }

    public function test_shop_uses_default_table_fees_when_configuration_is_missing(): void
    {
        config(['reservations.table_fees' => null]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)->get('/shop')
            ->assertOk()
            ->assertSee('value="2" data-fee="150.00"', false);
    }

    public function test_super_admin_can_change_party_size_prices_used_by_new_bookings(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($superAdmin)->put(route('settings.reservation-pricing.update'), [
            'table_fees' => [1 => 125, 2 => 225.50, 4 => 325, 8 => 525, 12 => 725],
        ])->assertRedirect()->assertSessionHas('status', 'Party size prices updated successfully.');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'reservation_table_fee_4',
            'value' => '325.00',
        ]);

        $this->actingAs($customer)->get(route('reservations.create'))
            ->assertOk()
            ->assertSee('value="4" data-fee="325.00"', false);

        $this->actingAs($customer)->post(route('reservations.store'), [
            'type' => 'table',
            'table_size' => 4,
            'customer_name' => $customer->name,
            'email' => $customer->email,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->setTime(12, 0)->format('Y-m-d H:i:s'),
            'payment_method' => 'cash',
        ])->assertRedirect();

        $reservation = Reservation::query()->whereBelongsTo($customer)->firstOrFail();
        $this->assertSame(325.0, (float) $reservation->reservation_fee);
        $this->assertSame(325.0, (float) $reservation->total_amount);
    }

    public function test_party_size_prices_require_valid_values_and_super_admin_access(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $fees = [1 => 100, 2 => 150, 4 => -1, 8 => 450, 12 => 650];

        $this->actingAs($superAdmin)->put(route('settings.reservation-pricing.update'), [
            'table_fees' => $fees,
        ])->assertSessionHasErrors('table_fees.4');

        $fees[4] = 250;
        $this->actingAs($admin)->put(route('settings.reservation-pricing.update'), [
            'table_fees' => $fees,
        ])->assertForbidden();

        $this->assertDatabaseMissing('system_settings', ['key' => 'reservation_table_fee_4']);
    }

    private function fakePng(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
