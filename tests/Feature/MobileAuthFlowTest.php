<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class MobileAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_resending_and_correcting_a_code_does_not_throttle_account_creation(): void
    {
        $code = null;
        Mail::shouldReceive('raw')->twice()->andReturnUsing(function (string $text) use (&$code): void {
            preg_match('/code is (\d{6})/', $text, $matches);
            $code = $matches[1];
        });
        $email = 'mobile.customer@gmail.com';
        $this->postJson('/api/v1/register/email', ['email' => $email])->assertOk();
        $challenge = $this->postJson('/api/v1/register/email', ['email' => $email])->assertOk()->json('data.challenge');
        $this->postJson('/api/v1/register/email/verify', [
            'challenge' => $challenge, 'email' => $email, 'code' => '000000',
        ])->assertUnprocessable();
        $token = $this->postJson('/api/v1/register/email/verify', [
            'challenge' => $challenge, 'email' => $email, 'code' => $code,
        ])->assertOk()->json('data.registration_token');

        $this->postJson('/api/v1/register', [
            'registration_token' => $token,
            'name' => 'Mobile Customer', 'username' => 'mobile.user',
            'email' => strtoupper($email), 'phone' => '09123456789',
            'birthday' => '2000-09-15', 'sex' => 'male', 'address' => 'Bantayan, Cebu',
            'password' => 'SecurePass123!', 'password_confirmation' => 'SecurePass123!',
        ])->assertCreated()->assertJsonPath('data.email', $email);
        $this->postJson('/api/v1/login', ['login' => $email, 'password' => 'SecurePass123!'])
            ->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_registration_requests_do_not_block_password_recovery_and_reset_allows_mobile_login(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/v1/register/email', [])->assertUnprocessable();
        }
        $this->postJson('/api/v1/register/email', [])->assertTooManyRequests();
        $this->postJson('/api/v1/password/forgot', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))->assertOk();
            $this->post(route('password.update'), [
                'token' => $notification->token, 'email' => $user->email,
                'password' => 'NewSecurePass123!', 'password_confirmation' => 'NewSecurePass123!',
            ])->assertRedirect(route('login'));

            return true;
        });
        $this->postJson('/api/v1/login', ['login' => $user->email, 'password' => 'NewSecurePass123!'])
            ->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_mail_failure_cleans_up_reset_token_so_an_immediate_retry_can_send(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('SMTP unavailable'));
        $this->postJson('/api/v1/password/forgot', ['email' => $user->email])
            ->assertStatus(503)->assertJsonPath('message', 'The password reset email could not be sent. Please try again later.');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        Notification::fake();
        $this->postJson('/api/v1/password/forgot', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_registration_mail_failure_is_an_actionable_error(): void
    {
        Mail::shouldReceive('raw')->once()->andThrow(new TransportException('SMTP unavailable'));
        $this->postJson('/api/v1/register/email', ['email' => 'new@gmail.com'])
            ->assertStatus(503)->assertJsonMissingPath('data.challenge')
            ->assertJsonPath('message', 'The verification email could not be sent. Please try again later.');
        $this->assertDatabaseMissing('users', ['email' => 'new@gmail.com']);
    }

    public function test_wrong_code_does_not_extend_the_original_expiry(): void
    {
        Mail::shouldReceive('raw')->once();
        $challenge = $this->postJson('/api/v1/register/email', ['email' => 'new@gmail.com'])
            ->assertOk()->json('data.challenge');
        $this->travel(9)->minutes();
        $this->postJson('/api/v1/register/email/verify', [
            'challenge' => $challenge, 'email' => 'new@gmail.com', 'code' => '000000',
        ])->assertUnprocessable();
        $this->travel(2)->minutes();
        $this->assertNull(Cache::get('mobile-registration-challenge:'.hash('sha256', $challenge)));
        $this->postJson('/api/v1/register/email/verify', [
            'challenge' => $challenge, 'email' => 'new@gmail.com', 'code' => '000000',
        ])->assertUnprocessable()->assertJsonPath('code', 'verification_expired');
    }
}
