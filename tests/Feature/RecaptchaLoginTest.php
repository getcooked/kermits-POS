<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecaptchaLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['url']->forceRootUrl('http://localhost');

        config(['services.recaptcha' => [
            'enabled' => true,
            'site_key' => 'test-site-key',
            'secret_key' => 'test-secret-key',
        ]]);
        Http::preventStrayRequests();
    }

    public function test_login_displays_widget_and_allows_required_google_resources_without_exposing_secret(): void
    {
        $response = $this->get('/login')->assertOk()
            ->assertSee('id="login-recaptcha"', false)
            ->assertSee('test-site-key')
            ->assertSee('https://www.google.com/recaptcha/api.js', false)
            ->assertDontSee('test-secret-key');

        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("#script-src 'self' 'nonce-[A-Za-z0-9]+' https://www\.google\.com/recaptcha/ https://www\.gstatic\.com/recaptcha/;#", $policy);
        $this->assertStringContainsString("frame-src 'self' https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/", $policy);
        $this->assertStringContainsString("connect-src 'self' https://www.google.com/recaptcha/", $policy);
    }

    public function test_valid_captcha_allows_login_and_sends_form_encoded_verification(): void
    {
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true, 'hostname' => 'localhost',
        ])]);
        $user = User::factory()->create(['password' => 'Password123!', 'role' => User::ROLE_CUSTOMER]);

        $this->post('/login', [
            'email' => $user->email, 'password' => 'Password123!', 'g-recaptcha-response' => 'valid-token',
        ])->assertRedirect('/shop');

        $this->assertAuthenticatedAs($user);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
            && $request->method() === 'POST'
            && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
            && $request['secret'] === 'test-secret-key'
            && $request['response'] === 'valid-token');
    }

    public function test_missing_captcha_blocks_correct_credentials_without_contacting_google(): void
    {
        Http::fake();
        $user = User::factory()->create(['password' => 'Password123!']);

        $this->from('/login')->post('/login', [
            'email' => $user->email, 'password' => 'Password123!',
        ])->assertRedirect('/login')->assertSessionHasErrors('g-recaptcha-response');

        $this->assertGuest();
        Http::assertNothingSent();
        $this->get('/login')->assertSee('Please complete the reCAPTCHA checkbox.');
    }

    #[DataProvider('failedVerifications')]
    public function test_failed_verification_blocks_correct_credentials(mixed $body, int $status): void
    {
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response($body, $status)]);
        $user = User::factory()->create(['password' => 'Password123!']);

        $this->from('/login')->post('/login', [
            'email' => $user->email, 'password' => 'Password123!', 'g-recaptcha-response' => 'bad-token',
        ])->assertRedirect('/login')->assertSessionHasErrors('g-recaptcha-response');

        $this->assertGuest();
        $this->assertNull(session('_old_input.password'));
    }

    public static function failedVerifications(): array
    {
        return [
            'invalid token' => [['success' => false, 'error-codes' => ['invalid-input-response']], 200],
            'expired or reused token' => [['success' => false, 'error-codes' => ['timeout-or-duplicate']], 200],
            'wrong hostname' => [['success' => true, 'hostname' => 'another.example'], 200],
            'missing hostname' => [['success' => true], 200],
            'malformed response' => ['not json', 200],
            'upstream failure' => [['success' => true, 'hostname' => 'localhost'], 503],
        ];
    }

    public function test_connection_failure_returns_a_retry_message(): void
    {
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::failedConnection()]);

        $this->post('/login', [
            'email' => 'customer@example.com', 'password' => 'Password123!', 'g-recaptcha-response' => 'token',
        ])->assertSessionHasErrors(['g-recaptcha-response' => 'Unable to contact reCAPTCHA. Please try again.']);
        $this->assertGuest();
    }

    public function test_enabled_captcha_with_missing_secret_fails_closed(): void
    {
        config(['services.recaptcha.secret_key' => null]);
        Http::fake();

        $this->post('/login', [
            'email' => 'customer@example.com', 'password' => 'Password123!', 'g-recaptcha-response' => 'token',
        ])->assertSessionHasErrors('g-recaptcha-response');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_valid_captcha_does_not_bypass_password_validation(): void
    {
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true, 'hostname' => 'localhost',
        ])]);

        $this->post('/login', [
            'email' => 'nobody@example.com', 'password' => 'incorrect', 'g-recaptcha-response' => 'valid-token',
        ])->assertSessionHasErrors('email')->assertSessionDoesntHaveErrors('g-recaptcha-response');

        $this->assertGuest();
    }

    public function test_disabled_captcha_does_not_render_the_widget(): void
    {
        config(['services.recaptcha.enabled' => false]);

        $this->get('/login')->assertOk()->assertDontSee('id="login-recaptcha"', false);
    }

    public function test_registration_and_password_recovery_do_not_display_recaptcha(): void
    {
        foreach (['/register', '/forgot-password', '/admin/forgot-password'] as $path) {
            $this->get($path)->assertOk()
                ->assertDontSee('id="registration-recaptcha"', false)
                ->assertDontSee('id="password-reset-recaptcha"', false)
                ->assertDontSee('https://www.google.com/recaptcha/api.js', false)
                ->assertDontSee('test-secret-key');
        }
    }

    public function test_registration_email_and_password_reset_work_without_captcha(): void
    {
        Mail::fake();
        Notification::fake();
        Http::fake();
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->post('/register/email', ['email' => 'new.customer@gmail.com'])
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();
        $this->assertSame('new.customer@gmail.com', session('registration_email_verification.email'));

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        Notification::assertSentTo($user, ResetPassword::class);
        Http::assertNothingSent();
    }
}
