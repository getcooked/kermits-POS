<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_cash_receipt_is_available_to_its_owner_and_super_admin_with_the_complete_total(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $otherCustomer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $product = Product::query()->create([
            'name' => 'Receipt Meal',
            'price' => 150,
            'stock' => 10,
            'active' => true,
        ]);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 300,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 150,
            'subtotal' => 300,
        ]);
        Reservation::query()->create([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'reference' => 'KRM-CASH-RECEIPT',
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
            'status' => 'pending',
        ]);

        $receiptUrl = route('receipts.show', $order);
        $this->actingAs($customer)->get($receiptUrl)
            ->assertOk()
            ->assertSee('PAYMENT PENDING')
            ->assertSee('Order Receipt')
            ->assertSee('Walk In Pay')
            ->assertSee('Pending')
            ->assertSee('KRM-CASH-RECEIPT')
            ->assertSee('4 seats')
            ->assertSee('Receipt Meal')
            ->assertSee('<span>Food subtotal <b>&#8369;300.00</b></span>', false)
            ->assertSee('<span>Reservation fee <b>&#8369;250.00</b></span>', false)
            ->assertSee('<strong><span>Total due</span><b>&#8369;550.00</b></strong>', false)
            ->assertSee('Print order receipt')
            ->assertDontSee('Official payment receipt');

        $this->actingAs($superAdmin)->get($receiptUrl)
            ->assertOk()
            ->assertSee('PAYMENT PENDING')
            ->assertSee('550.00')
            ->assertSee(route('reports'), false);

        $this->actingAs($otherCustomer)->get($receiptUrl)->assertForbidden();

        $this->actingAs($customer)->get(route('customer.history'))
            ->assertOk()
            ->assertSee('View receipt')
            ->assertSee($receiptUrl, false);

        $this->actingAs($superAdmin)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('View receipt')
            ->assertSee($receiptUrl, false);
    }

    public function test_gcash_receipt_shows_the_reference_without_exposing_the_proof_path_and_becomes_official_when_paid(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $product = Product::query()->create([
            'name' => 'GCash Receipt Meal',
            'price' => 175,
            'stock' => 10,
            'active' => true,
        ]);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 175,
            'payment_method' => 'gcash',
            'payment_status' => 'pending',
            'payment_reference' => '1234567890123',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 175,
            'subtotal' => 175,
        ]);
        $reservation = Reservation::query()->create([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'reference' => 'KRM-GCASH-RECEIPT',
            'type' => 'table',
            'table_size' => 2,
            'customer_name' => $customer->name,
            'email' => $customer->email,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay(),
            'guests' => 2,
            'reservation_fee' => 150,
            'food_total' => 0,
            'total_amount' => 150,
            'payment_method' => 'gcash',
            'payment_reference' => '1234567890123',
            'payment_status' => 'pending',
            'payment_proof_path' => 'payment-proofs/private-proof.png',
            'status' => 'pending',
        ]);

        $receiptUrl = route('receipts.show', $order);
        $this->actingAs($customer)->get($receiptUrl)
            ->assertOk()
            ->assertSee('PAYMENT PENDING')
            ->assertSee('GCash')
            ->assertSee('1234567890123')
            ->assertSee('<strong><span>Total due</span><b>&#8369;325.00</b></strong>', false)
            ->assertSee('GCash payment is awaiting verification')
            ->assertDontSee('payment-proofs/private-proof.png')
            ->assertDontSee('payment_proof_path');

        $this->actingAs($superAdmin)->get($receiptUrl)
            ->assertOk()
            ->assertSee('KRM-GCASH-RECEIPT')
            ->assertSee('1234567890123');

        $order->update(['payment_status' => 'paid']);
        $reservation->update(['payment_status' => 'paid']);

        $this->actingAs($customer)->get($receiptUrl)
            ->assertOk()
            ->assertSee('TRANSACTION COMPLETE')
            ->assertSee('Official Receipt')
            ->assertSee('<strong><span>Total paid</span><b>&#8369;325.00</b></strong>', false)
            ->assertSee('Print official receipt')
            ->assertSee('1234567890123');
    }

    public function test_paid_cash_receipt_shows_cash_received_and_change_against_the_combined_total(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 300,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'cash_received' => 600,
            'change_due' => 50,
        ]);
        Reservation::query()->create([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'reference' => 'KRM-PAID-CASH',
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
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        $this->actingAs($customer)->get(route('receipts.show', $order))
            ->assertOk()
            ->assertSee('TRANSACTION COMPLETE')
            ->assertSee('Official Receipt')
            ->assertSee('Paid')
            ->assertSee('<strong><span>Total paid</span><b>&#8369;550.00</b></strong>', false)
            ->assertSee('<span>Cash received <b>&#8369;600.00</b></span>', false)
            ->assertSee('<span>Change <b>&#8369;50.00</b></span>', false)
            ->assertSee('Print official receipt');
    }
}
