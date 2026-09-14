<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_their_accepted_and_rejected_order_notifications_only(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $otherCustomer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $accepted = Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 350,
            'payment_method' => 'gcash',
            'payment_status' => 'paid',
        ]);
        $rejected = Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 175,
            'payment_method' => 'cash',
            'payment_status' => 'rejected',
        ]);
        $pending = Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 999,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);
        $other = Order::query()->create([
            'user_id' => $otherCustomer->id,
            'customer_id' => $otherCustomer->id,
            'total' => 888,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->actingAs($customer)->get(route('customer.notifications'))
            ->assertOk()
            ->assertSee('Order #'.str_pad($accepted->id, 6, '0', STR_PAD_LEFT).' accepted')
            ->assertSee('Order #'.str_pad($rejected->id, 6, '0', STR_PAD_LEFT).' rejected')
            ->assertSee('350.00')
            ->assertSee('175.00')
            ->assertDontSee('999.00')
            ->assertDontSee('888.00')
            ->assertDontSee(route('shop.orders.show', $pending), false)
            ->assertDontSee(route('shop.orders.show', $other), false);
    }

    public function test_customer_navigation_shows_the_notification_button_and_decision_count(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        foreach (['paid', 'rejected', 'pending'] as $status) {
            Order::query()->create([
                'user_id' => $customer->id,
                'customer_id' => $customer->id,
                'total' => 100,
                'payment_method' => 'cash',
                'payment_status' => $status,
            ]);
        }

        $this->actingAs($customer)->get(route('customer.history'))
            ->assertOk()
            ->assertSee(route('customer.notifications'), false)
            ->assertSee('Notifications')
            ->assertSee('2 order updates');
    }

    public function test_guests_and_staff_cannot_open_customer_notifications(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $this->get(route('customer.notifications'))->assertRedirect(route('login'));
        $this->actingAs($cashier)->get(route('customer.notifications'))->assertForbidden();
    }
}
