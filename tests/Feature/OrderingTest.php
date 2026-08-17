<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_sale_is_paid_and_reduces_stock(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create([
            'name' => 'Test Product',
            'price' => 50,
            'stock' => 5,
            'active' => true,
        ]);

        $this->actingAs($cashier)->post('/cashier/checkout', [
            'quantities' => [$product->id => 2],
            'payment_method' => 'cash',
            'cash_received' => 150,
        ])->assertRedirect('/receipts/1');

        $this->assertDatabaseHas('orders', [
            'user_id' => $cashier->id,
            'total' => 100,
            'payment_status' => 'paid',
            'cash_received' => 150,
            'change_due' => 50,
        ]);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $cashier->id,
            'type' => 'sale',
            'quantity' => 2,
            'stock_before' => 5,
            'stock_after' => 3,
        ]);
    }

    public function test_cashier_cannot_complete_sale_when_customer_cash_is_insufficient(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create([
            'name' => 'Test Product',
            'price' => 100,
            'stock' => 5,
            'active' => true,
        ]);

        $this->actingAs($cashier)->post('/cashier/checkout', [
            'quantities' => [$product->id => 1],
            'payment_method' => 'cash',
            'cash_received' => 50,
        ])->assertSessionHasErrors('cash_received');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_cashier_can_complete_a_gcash_sale_with_a_verified_reference(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create(['name' => 'GCash Product', 'price' => 125, 'stock' => 5, 'active' => true]);

        $this->actingAs($cashier)->post('/cashier/checkout', [
            'quantities' => [$product->id => 2],
            'payment_method' => 'gcash',
            'payment_reference' => '1234567890123',
        ])->assertRedirect('/receipts/1');

        $this->assertDatabaseHas('orders', [
            'payment_method' => 'gcash',
            'payment_reference' => '1234567890123',
            'payment_status' => 'paid',
            'cash_received' => null,
            'change_due' => null,
        ]);
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_gcash_sale_requires_a_transaction_reference(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create(['name' => 'GCash Product', 'price' => 125, 'stock' => 5, 'active' => true]);

        $this->actingAs($cashier)->post('/cashier/checkout', [
            'quantities' => [$product->id => 1],
            'payment_method' => 'gcash',
        ])->assertSessionHasErrors('payment_reference');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_gcash_sale_requires_exactly_thirteen_numeric_reference_digits(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create(['name' => 'Reference Product', 'price' => 125, 'stock' => 5, 'active' => true]);

        $this->actingAs($cashier)->post('/cashier/checkout', [
            'quantities' => [$product->id => 1],
            'payment_method' => 'gcash',
            'payment_reference' => 'ABC1234567890',
        ])->assertSessionHasErrors('payment_reference');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unavailable_product_rejects_the_whole_order_without_changing_stock(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $available = Product::query()->create(['name' => 'Available', 'price' => 50, 'stock' => 5, 'active' => true]);
        $short = Product::query()->create(['name' => 'Short', 'price' => 20, 'stock' => 1, 'active' => true]);

        $this->actingAs($cashier)->post('/cashier/checkout', [
            'quantities' => [$available->id => 2, $short->id => 2],
            'payment_method' => 'cash',
            'cash_received' => 500,
        ])->assertSessionHasErrors('quantities');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $available->fresh()->stock);
        $this->assertSame(1, $short->fresh()->stock);
    }

    public function test_customer_can_only_open_their_own_order(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $otherCustomer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $order = Order::query()->create([
            'user_id' => $owner->id,
            'total' => 100,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);

        $this->actingAs($owner)->get('/shop/orders/'.$order->id)->assertOk();
        $this->actingAs($otherCustomer)->get('/shop/orders/'.$order->id)->assertForbidden();
    }

    public function test_pending_customer_orders_are_not_counted_as_paid_sales(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        Order::query()->create(['user_id' => $customer->id, 'total' => 999, 'payment_method' => 'cash', 'payment_status' => 'pending']);
        Order::query()->create(['user_id' => $admin->id, 'total' => 100, 'payment_method' => 'cash', 'payment_status' => 'paid']);

        $this->actingAs($admin)->get('/reports')
            ->assertOk()
            ->assertSee('<span>Total sales</span><strong>&#8369;100.00</strong>', false)
            ->assertDontSee('999.00')
            ->assertDontSee('Awaiting payment confirmation');
    }

    public function test_customer_can_choose_cash_or_gcash_for_an_online_order(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $product = Product::query()->create(['name' => 'Customer Payment Product', 'price' => 150, 'stock' => 10, 'active' => true]);

        $this->actingAs($customer)->post('/shop/orders', [
            'quantities' => [$product->id => 1],
            'payment_method' => 'gcash',
            'payment_reference' => '1234567890123',
        ])->assertRedirect('/shop/orders/1');

        $this->assertDatabaseHas('orders', [
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'payment_method' => 'gcash',
            'payment_status' => 'pending',
            'payment_reference' => '1234567890123',
        ]);
    }

    public function test_customer_gcash_order_requires_exactly_thirteen_reference_digits(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $product = Product::query()->create(['name' => 'Reference Product', 'price' => 100, 'stock' => 5, 'active' => true]);

        $this->actingAs($customer)->post('/shop/orders', [
            'quantities' => [$product->id => 1],
            'payment_method' => 'gcash',
            'payment_reference' => '12345',
        ])->assertSessionHasErrors('payment_reference');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cashier_can_confirm_customer_payment_and_connect_it_to_admin_sales_reports(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $product = Product::query()->create(['name' => 'Connected Online Sale', 'price' => 275, 'stock' => 5, 'active' => true]);
        $order = app(\App\Services\OrderService::class)->create(
            user: $customer,
            quantities: [$product->id => 1],
            paymentStatus: 'pending',
            paymentMethod: 'gcash',
            paymentReference: '1234567890123',
            customer: $customer,
        );

        $this->actingAs($cashier)->get(route('cashier.orders.index'))
            ->assertOk()
            ->assertSee('Customer orders')
            ->assertSee('Review order')
            ->assertSee('1234567890123')
            ->assertSee('Connected Online Sale');

        $this->actingAs($admin)->get(route('cashier.orders.index'))->assertForbidden();

        $this->actingAs($cashier)->get(route('cashier.orders.review', $order))
            ->assertOk()
            ->assertSee('Already submitted through GCash')
            ->assertSee('Verify GCash and confirm paid');

        $this->actingAs($admin)->get('/reports')
            ->assertOk()
            ->assertDontSee('Awaiting payment confirmation')
            ->assertDontSee('1234567890123');

        $this->actingAs($cashier)
            ->patch(route('cashier.orders.confirm-payment', $order))
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);

        $this->actingAs($admin)->get('/reports')
            ->assertOk()
            ->assertSee('275.00')
            ->assertSee('GCash · 1 sales');
    }

    public function test_customer_admin_and_super_admin_cannot_confirm_online_order_payments(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 100,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);

        foreach ([$customer, $admin, $superAdmin] as $user) {
            $this->actingAs($user)
                ->patch(route('cashier.orders.confirm-payment', $order))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'pending']);
    }

    public function test_admin_report_identifies_gcash_sales_and_supports_payment_filtering(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        Order::query()->create(['user_id' => $cashier->id, 'total' => 200, 'payment_method' => 'cash', 'payment_status' => 'paid']);
        Order::query()->create(['user_id' => $cashier->id, 'total' => 350, 'payment_method' => 'gcash', 'payment_status' => 'paid', 'payment_reference' => 'GCASH-REPORT-123']);

        $this->actingAs($admin)->get('/reports')
            ->assertOk()
            ->assertSee('GCash · 1 sales')
            ->assertSee('GCASH-REPORT-123');

        $this->actingAs($admin)->get('/reports?payment_method=cash')
            ->assertOk()
            ->assertSee('Filtered orders')
            ->assertDontSee('GCASH-REPORT-123');
    }

    public function test_admin_and_super_admin_share_connected_operational_reports(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        \App\Models\Reservation::query()->create([
            'user_id' => $customer->id,
            'reference' => 'KRM-REPORT-LIVE',
            'type' => 'table',
            'table_size' => 4,
            'customer_name' => $customer->name,
            'email' => $customer->email,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay(),
            'guests' => 4,
            'reservation_fee' => 250,
            'food_total' => 0,
            'total_amount' => 250,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'status' => 'confirmed',
        ]);

        foreach ([$admin, $superAdmin] as $staff) {
            $this->actingAs($staff)->get('/reports')
                ->assertOk()
                ->assertSee('Reservation report')
                ->assertSee('KRM-REPORT-LIVE')
                ->assertSee('Stock movement report')
                ->assertSee('Best-selling products');
        }
    }

    public function test_cashier_sales_are_always_recorded_as_walk_in_purchases(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'name' => 'Connected Customer']);
        $product = Product::query()->create(['name' => 'Connected Product', 'price' => 100, 'stock' => 5, 'active' => true]);

        $this->actingAs($cashier)->post('/cashier/checkout', [
            'customer_id' => $customer->id,
            'quantities' => [$product->id => 1],
            'payment_method' => 'cash',
            'cash_received' => 100,
        ])->assertRedirect('/receipts/1');

        $this->assertDatabaseHas('orders', ['customer_id' => null, 'user_id' => $cashier->id]);
        $this->actingAs($admin)->get('/reports')->assertOk()->assertSee('Walk-in Customer');
        $this->actingAs($customer)->get('/history')->assertOk()->assertDontSee('Connected Product');
    }

    public function test_staff_and_customer_catalogs_show_categories_only_in_the_filter_bar(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Product::query()->create(['name' => 'Category Drink', 'category' => 'Drinks', 'price' => 100, 'stock' => 5, 'active' => true]);
        Product::query()->create(['name' => 'Category Starter', 'category' => 'Starters', 'price' => 120, 'stock' => 5, 'active' => true]);

        $this->actingAs($cashier)
            ->get('/cashier')
            ->assertOk()
            ->assertSee('data-category-filter="Drinks"', false)
            ->assertSee('data-pos-scroll="-1"', false)
            ->assertDontSee('data-category-heading', false);

        $this->actingAs($superAdmin)
            ->get('/cashier')
            ->assertOk()
            ->assertSee('data-category-filter="Drinks"', false)
            ->assertSee('data-pos-scroll="-1"', false)
            ->assertDontSee('data-category-heading', false);

        $this->actingAs($customer)
            ->get('/shop')
            ->assertOk()
            ->assertSee('data-shop-category="Drinks"', false)
            ->assertSee('data-shop-scroll="-1"', false)
            ->assertDontSee('data-shop-heading', false);
    }
}
