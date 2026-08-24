<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MobileRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_mobile_app_can_request_a_gmail_verification_code(): void
    {
        Mail::shouldReceive('raw')->once();

        $this->postJson('/api/v1/register/email', ['email' => 'New.Customer@Gmail.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'new.customer@gmail.com')
            ->assertJsonStructure(['data' => ['challenge', 'email', 'expires_in']]);
    }

    public function test_verified_email_can_create_only_a_customer_account(): void
    {
        $challenge = str_repeat('a', 64);
        Cache::put('mobile-registration-challenge:'.hash('sha256', $challenge), [
            'email' => 'new.customer@gmail.com',
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
        ], now()->addMinutes(10));

        $token = $this->postJson('/api/v1/register/email/verify', [
            'challenge' => $challenge,
            'email' => 'new.customer@gmail.com',
            'code' => '123456',
        ])->assertOk()->json('data.registration_token');

        $this->postJson('/api/v1/register', [
            'registration_token' => $token,
            'name' => 'New Customer',
            'username' => 'new.customer',
            'email' => 'new.customer@gmail.com',
            'phone' => '09171234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => User::ROLE_SUPER_ADMIN,
        ])->assertCreated()->assertJsonPath('data.role', User::ROLE_CUSTOMER);

        $this->assertDatabaseHas('users', [
            'email' => 'new.customer@gmail.com',
            'role' => User::ROLE_CUSTOMER,
        ]);
        $this->assertNotNull(User::query()->where('email', 'new.customer@gmail.com')->firstOrFail()->email_verified_at);
    }

    public function test_unverified_email_cannot_create_an_account(): void
    {
        $this->postJson('/api/v1/register', [
            'registration_token' => str_repeat('x', 64),
            'name' => 'Unverified Customer',
            'username' => 'unverified',
            'email' => 'unverified@gmail.com',
            'phone' => '09171234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('users', ['email' => 'unverified@gmail.com']);
    }
}
