<?php

namespace Tests\Feature;

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

    private function fakePng(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
