<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_reset_link_can_be_requested_for_an_active_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user): bool {
                $response = $this->post(route('password.update'), [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'Secure12345',
                    'password_confirmation' => 'Secure12345',
                ]);

                $response->assertRedirect(route('login'));

                return true;
            }
        );

        $this->assertTrue(Hash::check('Secure12345', $user->fresh()->password));
    }
}
