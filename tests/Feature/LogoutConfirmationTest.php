<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRMED_LOGOUT = 'action="'.'%s'.'" data-confirm="You will need to sign in again to continue." data-confirm-title="Log out of Kermit\'s?" data-confirm-label="Log out"';

    public function test_every_role_confirms_before_logging_out(): void
    {
        $pages = [
            User::ROLE_SUPER_ADMIN => [route('dashboard'), route('settings.payment.edit')],
            User::ROLE_CASHIER => [route('cashier')],
            User::ROLE_CUSTOMER => [route('shop'), route('customer.history'), route('reservations.create')],
        ];

        foreach ($pages as $role => $urls) {
            $user = User::factory()->create(['role' => $role]);

            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)
                    ->assertOk()
                    ->assertSee(sprintf(self::CONFIRMED_LOGOUT, route('logout')), false);
            }
        }
    }
}
