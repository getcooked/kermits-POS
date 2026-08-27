<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_is_available_to_guests(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Reset your password');
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
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        Notification::assertSentTo($superAdmin, ResetPassword::class);
        Notification::assertNotSentTo($admin, ResetPassword::class);
    }

    public function test_reset_link_can_be_requested_for_an_active_user(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_mobile_password_recovery_sends_only_to_a_customer_account(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->postJson('/api/v1/password/forgot', ['email' => $customer->email])
            ->assertOk()
            ->assertJsonPath('message', 'If an active customer account uses that email address, a password reset link has been sent.');
        $this->postJson('/api/v1/password/forgot', ['email' => $admin->email])->assertOk();

        Notification::assertSentTo($customer, ResetPassword::class);
        Notification::assertNotSentTo($admin, ResetPassword::class);
    }

    public function test_reset_link_is_not_created_for_an_unknown_or_deleted_account(): void
    {
        Notification::fake();
        $deletedUser = User::factory()->create();
        $deletedUser->delete();

        $this->post(route('password.email'), ['email' => 'not-a-kermits-account@example.com'])
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $this->post(route('password.email'), ['email' => $deletedUser->email])
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
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
