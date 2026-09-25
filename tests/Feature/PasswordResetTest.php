<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_RESET_MESSAGE = 'If an eligible account exists, a password reset link has been sent.';

    public function test_forgot_password_page_is_available_to_guests(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Reset your password')
            ->assertSee('Your Kermit’s account will receive a reset link from this page. Thank You!');
    }

    public function test_super_admin_recovery_page_remains_available_without_a_login_page_link(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee(route('superadmin.password.request'));

        $this->get(route('superadmin.password.request'))
            ->assertOk()
            ->assertSee('Recover Super Admin access');
    }

    public function test_super_admin_recovery_sends_only_to_a_super_admin_account(): void
    {
        Notification::fake();
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->post(route('superadmin.password.email'), ['email' => $superAdmin->email])
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $this->post(route('superadmin.password.email'), ['email' => $admin->email])
            ->assertSessionHas('status', self::GENERIC_RESET_MESSAGE)
            ->assertSessionDoesntHaveErrors();

        Notification::assertSentTo($superAdmin, ResetPassword::class);
        Notification::assertNotSentTo($admin, ResetPassword::class);
    }

    public function test_new_email_can_recover_a_super_admin_before_the_data_migration_runs(): void
    {
        Notification::fake();
        $superAdmin = User::factory()->create([
            'email' => 'superadmin@gmail.com',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->post(route('superadmin.password.email'), ['email' => 'kermitsbantayan1@gmail.com'])
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('kermitsbantayan1@gmail.com', $superAdmin->fresh()->email);
        Notification::assertSentTo($superAdmin, ResetPassword::class);
    }

    public function test_reset_link_can_be_requested_for_an_active_user(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['role' => User::ROLE_CUSTOMER]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_web_reset_delivery_failure_removes_the_unsent_token(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('SMTP unavailable'));

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasErrors([
                'email' => 'We could not send the reset email right now. Please try again in a few minutes.',
            ])
            ->assertSessionMissing('status');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_mobile_reset_does_not_reveal_when_the_broker_throttles_delivery(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => $user->email])
            ->andReturn(Password::RESET_THROTTLED);

        $this->postJson('/api/v1/password/forgot', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', self::GENERIC_RESET_MESSAGE);
    }

    public function test_mobile_password_recovery_sends_only_to_a_customer_account(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->postJson('/api/v1/password/forgot', ['email' => $customer->email])
            ->assertOk()
            ->assertJsonPath('message', self::GENERIC_RESET_MESSAGE);
        $this->postJson('/api/v1/password/forgot', ['email' => $admin->email])
            ->assertOk()
            ->assertJsonPath('message', self::GENERIC_RESET_MESSAGE);

        Notification::assertSentTo($customer, ResetPassword::class);
        Notification::assertNotSentTo($admin, ResetPassword::class);
    }

    public function test_mobile_password_recovery_hides_unregistered_or_deleted_customer_status(): void
    {
        Notification::fake();
        $deletedCustomer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $deletedCustomer->delete();

        foreach (['not-registered@example.com', $deletedCustomer->email] as $email) {
            $this->postJson('/api/v1/password/forgot', ['email' => $email])
                ->assertOk()
                ->assertJsonPath('message', self::GENERIC_RESET_MESSAGE);
        }

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_web_password_recovery_hides_unknown_deleted_or_staff_account_status(): void
    {
        Notification::fake();
        $deletedUser = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $deletedUser->delete();
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        foreach (['not-a-kermits-account@example.com', $deletedUser->email, $cashier->email] as $email) {
            $this->post(route('password.email'), ['email' => $email])
                ->assertSessionHas('status', self::GENERIC_RESET_MESSAGE)
                ->assertSessionDoesntHaveErrors();
        }

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_reset_form_rejects_an_unknown_email_or_invalid_token(): void
    {
        $this->get(route('password.reset', [
            'token' => 'not-a-valid-token',
            'email' => 'not-registered@example.com',
        ]))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors([
                'email' => 'This password reset link is invalid, expired, or does not belong to a registered account.',
            ]);

        $this->post(route('password.update'), [
            'token' => 'not-a-valid-token',
            'email' => 'not-registered@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ])->assertSessionHasErrors([
            'email' => 'This password reset link is invalid or expired.',
        ]);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['role' => User::ROLE_CUSTOMER]);
        DB::table('sessions')->insert([
            'id' => 'active-web-session',
            'user_id' => $user->id,
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);
        DB::table('mobile_api_tokens')->insert([
            'user_id' => $user->id,
            'name' => 'Android phone',
            'token_hash' => hash('sha256', 'active-mobile-token'),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user): bool {
                $this->get(route('password.reset', [
                    'token' => $notification->token,
                    'email' => $user->email,
                ]))
                    ->assertOk()
                    ->assertSee('Registered email address')
                    ->assertSee('readonly', false);

                $response = $this->post(route('password.update'), [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'SecurePass123!',
                    'password_confirmation' => 'SecurePass123!',
                ]);

                $response->assertRedirect(route('login'));

                return true;
            }
        );

        $this->assertTrue(Hash::check('SecurePass123!', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('mobile_api_tokens', ['user_id' => $user->id]);
    }
}
