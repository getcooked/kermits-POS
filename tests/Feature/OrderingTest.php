<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        Order::query()->create(['user_id' => $customer->id, 'total' => 999, 'payment_method' => 'cash', 'payment_status' => 'pending']);
        Order::query()->create(['user_id' => $superAdmin->id, 'total' => 100, 'payment_method' => 'cash', 'payment_status' => 'paid']);

        $this->actingAs($superAdmin)->get('/reports')
            ->assertOk()
            ->assertSee('<span>Total sales</span><strong>&#8369;100.00</strong>', false)
            ->assertDontSee('999.00')
            ->assertDontSee('Awaiting payment confirmation');
    }

    public function test_customer_cash_checkout_creates_a_linked_order_and_table_reservation_then_shows_the_receipt(): void
    {
        $customer = User::factory()->create([
            'name' => 'Reservation Checkout Customer',
            'email' => 'reservation-checkout@gmail.com',
            'role' => User::ROLE_CUSTOMER,
        ]);
        $product = Product::query()->create([
            'name' => 'Reservation Checkout Meal',
            'price' => 250,
            'stock' => 5,
            'active' => true,
        ]);
        $reservationAt = now()->addDay()->startOfMinute();

        $this->actingAs($customer)->post('/shop/orders', [
            'quantities' => [$product->id => 1],
            'table_size' => 2,
            'phone' => '09171234567',
            'reservation_at' => $reservationAt->format('Y-m-d\TH:i'),
            'notes' => 'Window table, please.',
            'payment_method' => 'cash',
        ])->assertRedirect(route('shop.orders.show', 1));

        $order = Order::query()->with('reservation')->firstOrFail();
        $reservation = Reservation::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertTrue($order->reservation->is($reservation));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 250,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'payment_reference' => null,
        ]);
        $this->assertDatabaseHas('reservations', [
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'type' => 'table',
            'table_size' => 2,
            'guests' => 2,
            'phone' => '09171234567',
            'food_request' => null,
            'food_total' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'notes' => 'Window table, please.',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('reservation_items', 0);
        $this->assertSame(4, $product->fresh()->stock);

        $this->get(route('shop.orders.show', $order))
            ->assertOk()
            ->assertSee('Order Receipt')
            ->assertSee('Reservation Checkout Meal')
            ->assertSee('Walk In Pay');
    }

    public function test_customer_shop_uses_one_ordered_multi_step_checkout_dialog_without_food_request_fields(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $menu = $this->actingAs($customer)->get('/shop')
            ->assertOk()
            ->assertSee('>Menu</a>', false)
            ->assertDontSee('>Shop</a>', false)
            ->assertSee('data-menu-reserve', false)
            ->assertSee('data-checkout-modal', false)
            ->assertSee('data-checkout-step="reservation"', false)
            ->assertSee('name="table_size"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="reservation_at"', false)
            ->assertSee('name="notes"', false)
            ->assertSee('Submit reservation')
            ->assertSee('data-checkout-step="payment"', false)
            ->assertSee('Walk In Pay')
            ->assertSee('value="cash"', false)
            ->assertSee('GCash')
            ->assertSee('value="gcash"', false)
            ->assertDontSee('Food Request')
            ->assertDontSee('name="menu_items[', false)
            ->assertDontSee('name="food_request"', false)
            ->assertSeeInOrder([
                'data-checkout-step="reservation"',
                'Submit reservation',
                'data-checkout-step="payment"',
                'Walk In Pay',
                'GCash',
            ], false);

        $this->assertSame(1, substr_count($menu->getContent(), route('reservations.create')));

        foreach (['/history', '/book'] as $page) {
            $this->actingAs($customer)->get($page)
                ->assertOk()
                ->assertSee('>Menu</a>', false)
                ->assertDontSee('>Shop</a>', false)
                ->assertDontSee('>Reserve</a>', false);
        }
    }

    public function test_customer_gcash_checkout_requires_a_thirteen_digit_reference_and_image_proof(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $product = Product::query()->create(['name' => 'Reference Product', 'price' => 100, 'stock' => 5, 'active' => true]);

        $this->actingAs($customer)->post('/shop/orders', [
            'quantities' => [$product->id => 1],
            'table_size' => 2,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'payment_method' => 'gcash',
            'payment_reference' => '12345',
        ])->assertSessionHasErrors(['payment_reference', 'payment_proof']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_customer_can_complete_gcash_checkout_with_proof_and_receive_the_order_receipt(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $product = Product::query()->create(['name' => 'GCash Checkout Product', 'price' => 175, 'stock' => 5, 'active' => true]);

        $this->actingAs($customer)->post('/shop/orders', [
            'quantities' => [$product->id => 2],
            'table_size' => 4,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'payment_method' => 'gcash',
            'payment_reference' => '1234567890123',
            'payment_proof' => $this->fakePng('checkout-proof.png'),
        ])->assertRedirect(route('shop.orders.show', 1));

        $order = Order::query()->firstOrFail();
        $reservation = Reservation::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'gcash',
            'payment_reference' => '1234567890123',
            'payment_status' => 'pending',
        ]);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'order_id' => $order->id,
            'payment_method' => 'gcash',
            'payment_reference' => '1234567890123',
            'payment_status' => 'pending',
        ]);
        $this->assertNotNull($reservation->payment_proof_path);
        Storage::disk('local')->assertExists($reservation->payment_proof_path);

        $this->get(route('shop.orders.show', $order))
            ->assertOk()
            ->assertSee('Order Receipt')
            ->assertSee('GCash')
            ->assertSee('1234567890123');
    }

    public function test_customer_checkout_rolls_back_order_stock_and_uploaded_proof_when_reservation_creation_fails(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $product = Product::query()->create(['name' => 'Atomic Checkout Product', 'price' => 200, 'stock' => 5, 'active' => true]);

        Reservation::creating(function (): never {
            throw new RuntimeException('Simulated reservation persistence failure.');
        });

        try {
            $this->withoutExceptionHandling()->actingAs($customer)->post('/shop/orders', [
                'quantities' => [$product->id => 2],
                'table_size' => 2,
                'phone' => '09171234567',
                'reservation_at' => now()->addDay()->format('Y-m-d\TH:i'),
                'payment_method' => 'gcash',
                'payment_reference' => '1234567890123',
                'payment_proof' => $this->fakePng('rollback-proof.png'),
            ]);

            $this->fail('The simulated reservation failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated reservation persistence failure.', $exception->getMessage());
        } finally {
            Reservation::flushEventListeners();
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame([], Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_cashier_can_confirm_customer_payment_and_connect_it_to_super_admin_sales_reports(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $product = Product::query()->create(['name' => 'Connected Online Sale', 'price' => 275, 'stock' => 5, 'active' => true]);
        $order = app(OrderService::class)->create(
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

        $this->actingAs($superAdmin)->get('/reports')
            ->assertOk()
            ->assertDontSee('Awaiting payment confirmation')
            ->assertDontSee('1234567890123');

        $this->actingAs($cashier)
            ->patch(route('cashier.orders.confirm-payment', $order))
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);

        $this->actingAs($superAdmin)->get('/reports')
            ->assertOk()
            ->assertSee('275.00')
            ->assertSee('GCash · 1 sales');
    }

    public function test_cashier_collects_the_combined_order_and_table_fee_before_marking_linked_checkout_paid(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create([
            'name' => 'Combined Payment Meal',
            'price' => 200,
            'stock' => 5,
            'active' => true,
        ]);

        $this->actingAs($customer)->post('/shop/orders', [
            'quantities' => [$product->id => 1],
            'table_size' => 2,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'payment_method' => 'cash',
        ])->assertRedirect(route('shop.orders.show', 1));

        $order = Order::query()->with('reservation')->firstOrFail();
        $reservation = $order->reservation;

        $this->assertNotNull($reservation);
        $this->assertSame(350.0, $order->totalDue());

        $this->actingAs($cashier)
            ->patch(route('cashier.orders.confirm-payment', $order), ['cash_received' => 349.99])
            ->assertSessionHasErrors('cash_received');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'pending',
            'cash_received' => null,
            'change_due' => null,
        ]);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'order_id' => $order->id,
            'payment_status' => 'pending',
        ]);

        $this->actingAs($cashier)
            ->patch(route('cashier.orders.confirm-payment', $order), ['cash_received' => 400])
            ->assertRedirect(route('cashier.orders.index'));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'cash_received' => 400,
            'change_due' => 50,
        ]);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'order_id' => $order->id,
            'payment_status' => 'paid',
        ]);
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

    public function test_super_admin_report_identifies_gcash_sales_and_supports_payment_filtering(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        Order::query()->create(['user_id' => $cashier->id, 'total' => 200, 'payment_method' => 'cash', 'payment_status' => 'paid']);
        Order::query()->create(['user_id' => $cashier->id, 'total' => 350, 'payment_method' => 'gcash', 'payment_status' => 'paid', 'payment_reference' => 'GCASH-REPORT-123']);

        $this->actingAs($superAdmin)->get('/reports')
            ->assertOk()
            ->assertSee('GCash · 1 sales')
            ->assertSee('GCASH-REPORT-123');

        $this->actingAs($superAdmin)->get('/reports?payment_method=cash')
            ->assertOk()
            ->assertSee('Filtered orders')
            ->assertDontSee('GCASH-REPORT-123');
    }

    public function test_only_super_admin_can_access_connected_operational_reports(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        Reservation::query()->create([
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

        foreach ([$admin, $cashier, $customer] as $user) {
            $this->actingAs($user)->get('/reports')->assertForbidden();
        }

        $this->actingAs($superAdmin)->get('/reports')
            ->assertOk()
            ->assertSee('Reservation report')
            ->assertSee('KRM-REPORT-LIVE')
            ->assertSee('Stock movement report')
            ->assertSee('Best-selling products');
    }

    public function test_cashier_sales_are_always_recorded_as_walk_in_purchases(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
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
        $this->actingAs($superAdmin)->get('/reports')->assertOk()->assertSee('Walk-in Customer');
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

    private function fakePng(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
