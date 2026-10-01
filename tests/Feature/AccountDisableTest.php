<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountDisableTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_cashier_is_signed_out_and_cannot_log_in_until_enabled(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'Kim', 'email' => 'kim.cashier@gmail.com', 'password' => 'Cashier123!']);
        DB::table('sessions')->insert(['id' => 'cashier-session', 'user_id' => $cashier->id, 'payload' => 'test', 'last_activity' => now()->timestamp]);

        $this->actingAs($superAdmin)->patch(route('cashiers.disable', $cashier))
            ->assertSessionHas('status', "Kim's cashier account was disabled.");
        $this->assertNotNull($cashier->fresh()->disabled_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'cashier-session']);

        $this->actingAs($cashier->fresh())->get(route('cashier'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.store'), ['email' => 'kim.cashier@gmail.com', 'password' => 'Cashier123!'])
            ->assertSessionHasErrors(['email' => 'This account has been disabled. Please contact an Admin.']);

        $this->actingAs($superAdmin)->patch(route('cashiers.enable', $cashier))
            ->assertSessionHas('status', "Kim's cashier account was enabled.");
        $this->assertNull($cashier->fresh()->disabled_at);
    }

    public function test_disabled_customer_is_blocked_on_the_website_and_mobile_app(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'name' => 'Lia', 'email' => 'lia@gmail.com', 'password' => 'MobilePassword123!']);
        $token = $this->postJson('/api/v1/login', ['login' => 'lia@gmail.com', 'password' => 'MobilePassword123!'])
            ->assertOk()
            ->json('data.token');

        $this->actingAs($superAdmin)->patch(route('customers.disable', $customer))
            ->assertSessionHas('status', "Lia's customer account was disabled.");
        auth()->logout();

        // Existing app sessions stop working, and signing in again is refused.
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertDatabaseMissing('mobile_api_tokens', ['user_id' => $customer->id]);
        $this->postJson('/api/v1/login', ['login' => 'lia@gmail.com', 'password' => 'MobilePassword123!'])
            ->assertUnprocessable()
            ->assertJsonPath('message', "This account has been disabled. Please contact Kermit's for help.");
        $this->actingAs($customer->fresh())->get(route('shop'))->assertRedirect(route('login'));

        // A token that survived somehow is still refused while the account is disabled.
        auth()->logout();
        $this->actingAs($superAdmin)->patch(route('customers.enable', $customer));
        auth()->logout();
        $token = $this->postJson('/api/v1/login', ['login' => 'lia@gmail.com', 'password' => 'MobilePassword123!'])->assertOk()->json('data.token');
        $customer->fresh()->forceFill(['disabled_at' => now()])->save();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_only_super_admins_can_disable_accounts_and_roles_must_match(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        foreach ([User::ROLE_ADMIN, User::ROLE_CASHIER, User::ROLE_CUSTOMER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->patch(route('cashiers.disable', $cashier))->assertForbidden();
            $this->patch(route('customers.disable', $customer))->assertForbidden();
        }

        $this->actingAs($superAdmin)->patch(route('cashiers.disable', $customer))->assertNotFound();
        $this->actingAs($superAdmin)->patch(route('customers.disable', $cashier))->assertNotFound();
        $this->assertNull($cashier->fresh()->disabled_at);
        $this->assertNull($customer->fresh()->disabled_at);
    }

    public function test_account_pages_share_the_search_status_filters_and_switches(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'Active Cashier']);
        User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'Former Cashier', 'disabled_at' => now()]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'name' => 'Regular Customer']);

        $this->actingAs($superAdmin)->get(route('cashiers.index'))
            ->assertOk()
            ->assertSee('id="cashier-search"', false)
            ->assertSee('data-ad-search', false)
            ->assertSeeInOrder(['Cashier Accounts', '2', 'Active Cashier Accounts', '1', 'Disabled Cashier Accounts', '1'])
            ->assertSee(route('cashiers.disable', $cashier), false)
            ->assertSee('data-status="disabled"', false)
            ->assertSee('Add Cashier');

        $this->actingAs($superAdmin)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('id="customer-search"', false)
            ->assertSee(route('customers.disable', $customer), false)
            ->assertSee(route('customers.show', $customer), false);

        $this->actingAs($superAdmin)->get(route('superadmin.security.edit'))
            ->assertOk()
            ->assertSee('id="admin-search"', false)
            ->assertSee('Signed in');
    }
}
