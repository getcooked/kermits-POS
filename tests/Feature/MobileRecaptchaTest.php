<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MobileRecaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['url']->forceRootUrl('http://localhost');

        config(['services.recaptcha' => [
            'enabled' => true,
            'site_key' => 'shared-web-site-key',
            'secret_key' => 'shared-web-secret-key',
        ]]);
    }

    public function test_mobile_configuration_uses_the_web_recaptcha_enable_flag_without_exposing_keys(): void
    {
        $this->getJson('/api/v1/recaptcha/config')->assertOk()
            ->assertExactJson(['data' => ['enabled' => true]]);
    }

    public function test_mobile_challenge_page_renders_the_shared_web_key_without_the_secret(): void
    {
        $this->get('/mobile/recaptcha')->assertOk()
            ->assertSee('id="mobile-recaptcha"', false)
            ->assertSee('shared-web-site-key')
            ->assertSee('kermits-recaptcha://success?token=', false)
            ->assertDontSee('shared-web-secret-key');
    }

    public function test_missing_token_blocks_login_registration_email_and_recovery(): void
    {
        Http::fake();
        Mail::fake();
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->postJson('/api/v1/login', ['login' => $user->email, 'password' => 'Password123!'])
            ->assertUnprocessable()->assertJsonValidationErrors('recaptcha_token');
        $this->postJson('/api/v1/register/email', ['email' => 'new@gmail.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('recaptcha_token');
        $this->postJson('/api/v1/password/forgot', ['email' => $user->email])
            ->assertUnprocessable()->assertJsonValidationErrors('recaptcha_token');

        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_valid_shared_web_token_allows_mobile_login(): void
    {
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response($this->verification())]);
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => 'Password123!']);

        $this->postJson('/api/v1/login', [
            'login' => $user->email, 'password' => 'Password123!', 'recaptcha_token' => 'fresh-token',
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
            && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
            && $request['secret'] === 'shared-web-secret-key'
            && $request['response'] === 'fresh-token');
    }

    public function test_token_from_another_hostname_is_rejected(): void
    {
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response($this->verification('other.example'))]);
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => 'Password123!']);

        $this->postJson('/api/v1/login', [
            'login' => $user->email, 'password' => 'Password123!', 'recaptcha_token' => 'wrong-host-token',
        ])->assertUnprocessable()->assertJsonValidationErrors('recaptcha_token');
    }

    public function test_registration_and_recovery_use_shared_web_verification(): void
    {
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response($this->verification())]);
        Mail::fake();
        Notification::fake();
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->postJson('/api/v1/register/email', [
            'email' => 'new@gmail.com', 'recaptcha_token' => 'signup-token',
        ])->assertOk();
        $this->postJson('/api/v1/password/forgot', [
            'email' => $user->email, 'recaptcha_token' => 'recovery-token',
        ])->assertOk();

        Http::assertSent(fn (Request $request) => $request['response'] === 'signup-token');
        Http::assertSent(fn (Request $request) => $request['response'] === 'recovery-token');
    }

    private function verification(string $hostname = 'localhost'): array
    {
        return ['success' => true, 'hostname' => $hostname];
    }
}
