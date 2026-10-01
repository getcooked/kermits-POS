<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CustomerPasswordResetCode;
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
            'name' => 'Mobile Customer',
            'email' => strtoupper($email), 'phone' => '09123456789',
            'birthday' => '2000-09-15', 'sex' => 'male', 'address' => 'Bantayan, Cebu',
            'password' => 'SecurePass123!', 'password_confirmation' => 'SecurePass123!',
        ])->assertCreated()->assertJsonPath('data.email', $email);
        $this->postJson('/api/v1/login', ['login' => $email, 'password' => 'SecurePass123!'])
            ->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_registration_requests_do_not_block_password_recovery_and_in_app_reset_allows_mobile_login(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => 'OldSecurePass123!']);
        $oldToken = $this->postJson('/api/v1/login', ['login' => $user->email, 'password' => 'OldSecurePass123!'])
            ->assertOk()->json('data.token');
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/v1/register/email', [])->assertUnprocessable();
        }
        $this->postJson('/api/v1/register/email', [])->assertTooManyRequests();
        $challenge = $this->postJson('/api/v1/password/forgot', ['email' => strtoupper($user->email)])
            ->assertOk()->assertJsonPath('data.email', strtolower($user->email))->json('data.challenge');

        $code = null;
        Notification::assertSentTo($user, CustomerPasswordResetCode::class, function (CustomerPasswordResetCode $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });
        $reset = fn (string $code) => $this->postJson('/api/v1/password/reset', [
            'challenge' => $challenge, 'email' => $user->email, 'code' => $code,
            'password' => 'NewSecurePass123!', 'password_confirmation' => 'NewSecurePass123!',
        ]);
        $reset($code === '000000' ? '111111' : '000000')->assertUnprocessable()->assertJsonPath('message', 'The reset code is incorrect.');
        $reset($code)->assertOk();
        $reset($code)->assertUnprocessable()->assertJsonPath('code', 'verification_expired');

        $this->withToken($oldToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->postJson('/api/v1/login', ['login' => $user->email, 'password' => 'NewSecurePass123!'])
            ->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_unknown_email_gets_an_indistinguishable_challenge_that_never_resets(): void
    {
        Notification::fake();
        $challenge = $this->postJson('/api/v1/password/forgot', ['email' => 'nobody@gmail.com'])
            ->assertOk()->assertJsonPath('message', 'If an eligible account exists, a 6-digit password reset code has been sent.')
            ->json('data.challenge');
        Notification::assertNothingSent();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/password/reset', [
                'challenge' => $challenge, 'email' => 'nobody@gmail.com', 'code' => sprintf('%06d', $attempt),
                'password' => 'NewSecurePass123!', 'password_confirmation' => 'NewSecurePass123!',
            ])->assertUnprocessable()->assertJsonPath('message', 'The reset code is incorrect.');
        }
        $this->postJson('/api/v1/password/reset', [
            'challenge' => $challenge, 'email' => 'nobody@gmail.com', 'code' => '999999',
            'password' => 'NewSecurePass123!', 'password_confirmation' => 'NewSecurePass123!',
        ])->assertUnprocessable()->assertJsonPath('code', 'verification_attempts_exceeded');
    }

    public function test_mail_failure_discards_the_reset_code_so_an_immediate_retry_can_send(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('SMTP unavailable'));
        $challenge = $this->postJson('/api/v1/password/forgot', ['email' => $user->email])
            ->assertOk()->assertJsonPath('message', 'If an eligible account exists, a 6-digit password reset code has been sent.')
            ->json('data.challenge');
        $this->assertNull(Cache::get('mobile-password-reset-challenge:'.hash('sha256', $challenge)));

        Notification::fake();
        $this->postJson('/api/v1/password/forgot', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, CustomerPasswordResetCode::class);
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
