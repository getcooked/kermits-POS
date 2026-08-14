<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WebSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockPath = storage_path('app/web-seeder.lock');
        File::delete($this->lockPath);
        config(['web-seeder.enabled' => true, 'web-seeder.key' => str_repeat('a', 32)]);
    }

    protected function tearDown(): void
    {
        File::delete($this->lockPath);
        parent::tearDown();
    }

    public function test_disabled_setup_page_is_not_publicly_accessible(): void
    {
        config(['web-seeder.enabled' => false]);
        $this->get('/seeder')->assertNotFound();
    }

    public function test_invalid_deployment_key_cannot_create_accounts(): void
    {
        $this->post('/seeder', [
            'deployment_key' => str_repeat('b', 32),
            'password' => 'PrivatePassword123!',
            'password_confirmation' => 'PrivatePassword123!',
        ])->assertSessionHasErrors('deployment_key');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_valid_setup_creates_every_role_and_locks_itself(): void
    {
        $this->post('/seeder', [
            'deployment_key' => str_repeat('a', 32),
            'password' => 'PrivatePassword123!',
            'password_confirmation' => 'PrivatePassword123!',
        ])->assertRedirect('/seeder');

        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseHas('users', ['username' => 'superadmin', 'role' => User::ROLE_SUPER_ADMIN]);
        $this->assertDatabaseHas('users', ['username' => 'admin', 'role' => User::ROLE_ADMIN]);
        $this->assertDatabaseHas('users', ['username' => 'cashier', 'role' => User::ROLE_CASHIER]);
        $this->assertDatabaseHas('users', ['username' => 'customer', 'role' => User::ROLE_CUSTOMER]);
        $this->assertFileExists($this->lockPath);

        $this->get('/seeder')->assertSee('Setup complete');
    }
}
