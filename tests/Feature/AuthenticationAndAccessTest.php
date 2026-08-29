<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAndAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_is_redirected_to_its_own_home_page(): void
    {
        foreach ([
            User::ROLE_SUPER_ADMIN => '/dashboard',
            User::ROLE_ADMIN => '/',
            User::ROLE_CASHIER => '/cashier',
            User::ROLE_CUSTOMER => '/shop',
        ] as $role => $destination) {
            $user = User::factory()->create([
                'email' => $role.'@example.com',
                'password' => 'password123',
                'role' => $role,
            ]);

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])->assertRedirect($destination);

            $this->post('/logout')->assertRedirect('/login');
        }
    }

    public function test_guest_can_view_the_login_page_without_redirecting_to_home(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in to your account');
    }

    public function test_customer_registration_cannot_choose_a_staff_role(): void
    {
        $this->withSession([
            'registration_email_verification' => [
                'email' => 'buyer@gmail.com',
                'code_hash' => Hash::make('123456'),
                'expires_at' => now()->addMinutes(10)->timestamp,
                'verified' => true,
            ],
        ])->post('/register', [
            'name' => 'Customer Account',
            'username' => 'buyer.account',
            'email' => 'buyer@gmail.com',
            'phone' => '09171234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => User::ROLE_SUPER_ADMIN,
        ])->assertRedirect('/shop');

        $this->assertDatabaseHas('users', [
            'username' => 'buyer.account',
            'email' => 'buyer@gmail.com',
            'phone' => '09171234567',
            'role' => User::ROLE_CUSTOMER,
        ]);
    }

    public function test_customer_can_log_in_with_username_or_email(): void
    {
        $customer = User::factory()->create([
            'username' => 'kim.lloyd',
            'email' => 'kim@gmail.com',
            'password' => 'password123',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $this->post('/login', ['email' => $customer->username, 'password' => 'password123'])
            ->assertRedirect('/shop');
    }

    public function test_only_super_admin_can_edit_customer_login_details_and_reset_password(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $update = [
            'name' => 'Updated Customer',
            'username' => 'updated.customer',
            'email' => 'updated@gmail.com',
            'phone' => '09181234567',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];

        $this->actingAs($admin)->put('/customers/'.$customer->id, $update)->assertForbidden();
        $this->actingAs($superAdmin)->put('/customers/'.$customer->id, $update)->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'Updated Customer',
            'username' => 'updated.customer',
            'email' => 'updated@gmail.com',
            'phone' => '09181234567',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $this->post('/logout');
        $this->post('/login', ['email' => 'updated.customer', 'password' => 'SecurePass123!'])
            ->assertRedirect('/shop');
    }

    public function test_only_super_admin_can_delete_customer_account_while_preserving_transaction_records(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create([
            'username' => 'customer.delete',
            'email' => 'delete@gmail.com',
            'phone' => '09171234567',
            'password' => 'password123',
            'role' => User::ROLE_CUSTOMER,
        ]);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 100,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);

        $this->actingAs($admin)->delete('/customers/'.$customer->id)->assertForbidden();
        $this->actingAs($superAdmin)->delete('/customers/'.$customer->id)->assertRedirect('/customers');

        $this->assertSoftDeleted('users', ['id' => $customer->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'customer_id' => $customer->id]);
        $this->assertSame('Deleted Customer #'.$customer->id, $order->fresh()->customer->name);

        $this->post('/logout');
        $this->post('/login', ['email' => 'customer.delete', 'password' => 'password123'])
            ->assertSessionHasErrors('email');
    }

    public function test_only_super_admin_can_create_cashier_accounts(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cashierData = [
            'name' => 'New Cashier',
            'username' => 'new.cashier',
            'email' => 'cashier@gmail.com',
            'phone' => '09191234567',
            'password' => 'CashierPass123!',
            'password_confirmation' => 'CashierPass123!',
            'role' => User::ROLE_SUPER_ADMIN,
        ];

        $this->actingAs($admin)->get('/staff/cashiers')->assertForbidden();
        $this->actingAs($admin)->post('/staff/cashiers', $cashierData)->assertForbidden();
        $this->actingAs($superAdmin)->get('/staff/cashiers')->assertOk();
        $this->actingAs($superAdmin)->post('/staff/cashiers', $cashierData)->assertRedirect();

        $this->assertDatabaseHas('users', [
            'name' => 'New Cashier',
            'username' => 'new.cashier',
            'email' => 'cashier@gmail.com',
            'phone' => '09191234567',
            'role' => User::ROLE_CASHIER,
        ]);

        $this->post('/logout');
        $this->post('/login', ['email' => 'new.cashier', 'password' => 'CashierPass123!'])
            ->assertRedirect('/cashier');
    }

    public function test_only_super_admin_can_edit_and_delete_cashier_accounts_without_losing_sales(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cashier = User::factory()->create([
            'username' => 'cashier.old',
            'email' => 'oldcashier@gmail.com',
            'phone' => '09171234567',
            'password' => 'password123',
            'role' => User::ROLE_CASHIER,
        ]);
        $order = Order::query()->create([
            'user_id' => $cashier->id,
            'total' => 200,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        $update = [
            'name' => 'Updated Cashier',
            'username' => 'cashier.updated',
            'email' => 'updatedcashier@gmail.com',
            'phone' => '09181234567',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $this->actingAs($admin)->put('/staff/cashiers/'.$cashier->id, $update)->assertForbidden();
        $this->actingAs($superAdmin)->put('/staff/cashiers/'.$cashier->id, $update)->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $cashier->id, 'username' => 'cashier.updated', 'role' => User::ROLE_CASHIER]);

        $this->actingAs($admin)->delete('/staff/cashiers/'.$cashier->id)->assertForbidden();
        $this->actingAs($superAdmin)->delete('/staff/cashiers/'.$cashier->id)->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $cashier->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $cashier->id]);
        $this->assertSame('Deleted Cashier #'.$cashier->id, $order->fresh()->user->name);
    }

    public function test_customers_are_blocked_from_every_staff_area(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        foreach (['/dashboard', '/customers', '/reports', '/inventory', '/products', '/cashier'] as $path) {
            $this->actingAs($customer)->get($path)->assertForbidden();
        }

        $this->actingAs($customer)->get('/shop')->assertOk();
        $this->actingAs($customer)->get('/book')->assertOk();
    }

    public function test_only_super_admin_can_access_administrative_pages(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        foreach (['/dashboard', '/customers', '/reports', '/inventory', '/products', '/reservations', '/activity-logs'] as $path) {
            $this->actingAs($superAdmin)->get($path)->assertOk();
            $this->actingAs($admin)->get($path)->assertForbidden();
        }

        $this->actingAs($admin)->get('/cashier')->assertForbidden();

        $this->actingAs($cashier)->get('/cashier')->assertOk();
        $this->actingAs($cashier)->get('/reports')->assertForbidden();
        $this->actingAs($cashier)->get('/inventory')->assertForbidden();
    }

    public function test_super_admin_dashboard_uses_the_complete_shared_navigation(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)->get('/dashboard')
            ->assertOk()
            ->assertSee(route('dashboard'), false)
            ->assertSee(route('superadmin.security.edit'), false)
            ->assertSee(route('cashier'), false)
            ->assertSee(route('reports'), false)
            ->assertSee(route('inventory.index'), false)
            ->assertSee(route('reservations.index'), false)
            ->assertSee(route('products.index'), false)
            ->assertSee(route('admins.index'), false)
            ->assertSee(route('cashiers.index'), false)
            ->assertSee(route('activity-logs.index'), false)
            ->assertSee(route('settings.payment.edit'), false)
            ->assertSee(route('customers.index'), false);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_web_login_shows_an_inline_thirty_second_lockout_after_five_failures(): void
    {
        $this->freezeSecond();
        $customer = User::factory()->create([
            'email' => 'web.lockout@example.com',
            'password' => 'CorrectPassword123!',
            'role' => User::ROLE_CUSTOMER,
        ]);
        $invalidCredentials = [
            'email' => $customer->email,
            'password' => 'IncorrectPassword123!',
        ];

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->from(route('login'))->post(route('login.store'), $invalidCredentials)
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors([
                    'email' => 'The username/email or password is incorrect.',
                ])
                ->assertSessionMissing('login_retry_after');
        }

        $this->from(route('login'))->post(route('login.store'), $invalidCredentials)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Too many login attempts. Try again in 30 seconds.',
            ])
            ->assertSessionHas('login_retry_after', 30);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('id="login-lockout"', false)
            ->assertSee('data-retry-after="30"', false)
            ->assertSee('Too many login attempts. Try again in')
            ->assertDontSee('Too Many Requests');

        $this->travel(29)->seconds();

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $customer->email,
            'password' => 'CorrectPassword123!',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Too many login attempts. Try again in 1 seconds.',
            ])
            ->assertSessionHas('login_retry_after', 1);
        $this->assertGuest();

        $this->travel(1)->seconds();

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $customer->email,
            'password' => 'CorrectPassword123!',
        ])->assertRedirect('/shop');
        $this->assertAuthenticatedAs($customer);
    }
}
