<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_sees_only_sales_they_processed(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'Primary Cashier']);
        $otherCashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'Other Cashier']);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'name' => 'Included Customer']);
        $otherCustomer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'name' => 'Excluded Customer']);

        $included = $this->paidOrder($cashier, $customer, 350, 'cash');
        $this->paidOrder($otherCashier, $otherCustomer, 999, 'gcash');
        Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 800,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);

        $this->actingAs($cashier)
            ->get(route('sales-history.index'))
            ->assertOk()
            ->assertSee('My sales history')
            ->assertSee('Included Customer')
            ->assertSee('#'.str_pad((string) $included->id, 6, '0', STR_PAD_LEFT))
            ->assertSee('&#8369;350.00', false)
            ->assertDontSee('Excluded Customer')
            ->assertDontSee('&#8369;999.00', false);
    }

    public function test_confirming_an_online_order_attributes_the_sale_to_the_cashier(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 275,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);

        $this->actingAs($cashier)
            ->patch(route('cashier.orders.confirm-payment', $order), ['cash_received' => 300])
            ->assertRedirect(route('receipts.show', $order));

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame($cashier->id, $order->processed_by);
        $this->assertNotNull($order->paid_at);

        $this->get(route('sales-history.index'))
            ->assertOk()
            ->assertSee('#'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT));
    }

    public function test_super_admin_can_compare_and_filter_cashier_sales(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $cashierA = User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'Ana Cashier']);
        $cashierB = User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'Ben Cashier']);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $anaOrder = $this->paidOrder($cashierA, null, 200, 'cash');
        $benOrder = $this->paidOrder($cashierB, null, 450, 'gcash');
        Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 125,
            'payment_method' => 'paymongo',
            'payment_status' => 'paid',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('sales-history.index'))
            ->assertOk()
            ->assertSee('Cashier sales history')
            ->assertSee('Ana Cashier')
            ->assertSee('Ben Cashier')
            ->assertSee('Online / System')
            ->assertSee('&#8369;775.00', false);

        $this->get(route('sales-history.index', ['cashier_id' => $cashierA->id]))
            ->assertOk()
            ->assertSee('&#8369;200.00', false)
            ->assertSee('#'.str_pad((string) $anaOrder->id, 6, '0', STR_PAD_LEFT))
            ->assertDontSee('#'.str_pad((string) $benOrder->id, 6, '0', STR_PAD_LEFT));
    }

    public function test_cashier_csv_export_is_scoped_to_their_own_sales(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'Export Cashier']);
        $otherCashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $includedCustomer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'name' => 'CSV Included']);
        $excludedCustomer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'name' => 'CSV Excluded']);
        $this->paidOrder($cashier, $includedCustomer, 150, 'cash');
        $this->paidOrder($otherCashier, $excludedCustomer, 300, 'cash');

        $response = $this->actingAs($cashier)->get(route('sales-history.export'));

        $response->assertOk()->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('CSV Included', $csv);
        $this->assertStringContainsString('Export Cashier', $csv);
        $this->assertStringNotContainsString('CSV Excluded', $csv);
    }

    public function test_sales_history_rejects_users_without_staff_sales_access(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->get(route('sales-history.index'))->assertRedirect(route('login'));
        $this->actingAs($admin)->get(route('sales-history.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('sales-history.index'))->assertForbidden();
    }

    private function paidOrder(User $cashier, ?User $customer, float $total, string $paymentMethod): Order
    {
        return Order::query()->create([
            'user_id' => $customer?->id ?? $cashier->id,
            'customer_id' => $customer?->id,
            'processed_by' => $cashier->id,
            'total' => $total,
            'payment_method' => $paymentMethod,
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
