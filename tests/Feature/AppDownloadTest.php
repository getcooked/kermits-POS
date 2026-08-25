<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AppDownloadTest extends TestCase
{
    use RefreshDatabase;

    private string $releasePath;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('mobile.download_enabled', false);
        $this->releasePath = storage_path('app/releases/kermits.apk');
        File::delete($this->releasePath);
    }

    protected function tearDown(): void
    {
        File::delete($this->releasePath);
        parent::tearDown();
    }

    public function test_landing_page_shows_app_as_coming_soon_without_a_release(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('App coming soon')
            ->assertDontSee('Download app');

        $this->get('/download-app')->assertNotFound();
    }

    public function test_android_release_can_be_downloaded_from_the_landing_page(): void
    {
        File::put($this->releasePath, 'test apk');
        config()->set('mobile.download_enabled', true);

        $this->get('/')
            ->assertOk()
            ->assertSee('Download app')
            ->assertSee(route('app.download'));

        $this->get('/download-app')
            ->assertOk()
            ->assertDownload('Kermits-Restaurant.apk');
    }

    public function test_customer_pages_show_the_current_app_download_state(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $pages = [route('shop'), route('customer.history'), route('reservations.create')];

        foreach ($pages as $page) {
            $this->actingAs($customer)->get($page)
                ->assertOk()
                ->assertSee('App coming soon');
        }

        File::put($this->releasePath, 'test apk');
        config()->set('mobile.download_enabled', true);

        foreach ($pages as $page) {
            $this->actingAs($customer)->get($page)
                ->assertOk()
                ->assertSee('Download app')
                ->assertSee(route('app.download'));
        }
    }
}
