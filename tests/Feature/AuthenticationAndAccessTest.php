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
            User::ROLE_ADMIN => '/dashboard',
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
            'password' => 'password123',
            'password_confirmation' => 'password123',
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
            'password' => 'securepass123',
            'password_confirmation' => 'securepass123',
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
        $this->post('/login', ['email' => 'updated.customer', 'password' => 'securepass123'])
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
            'password' => 'cashierpass123',
            'password_confirmation' => 'cashierpass123',
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
        $this->post('/login', ['email' => 'new.cashier', 'password' => 'cashierpass123'])
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
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
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

    public function test_admin_and_cashier_permissions_are_separated(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/reports')->assertOk();
        $this->actingAs($admin)->get('/inventory')->assertOk();
        $this->actingAs($admin)->get('/cashier')->assertForbidden();

        $this->actingAs($cashier)->get('/cashier')->assertOk();
        $this->actingAs($cashier)->get('/reports')->assertForbidden();
        $this->actingAs($cashier)->get('/inventory')->assertForbidden();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
