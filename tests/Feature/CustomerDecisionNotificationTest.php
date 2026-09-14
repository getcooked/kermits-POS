<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\CustomerDecisionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerDecisionNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_is_emailed_when_reservations_are_accepted_or_rejected(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $accepted = $this->reservation($customer, 'KRM-ACCEPTED');
        $rejected = $this->reservation($customer, 'KRM-REJECTED');

        $this->actingAs($superAdmin)
            ->patch(route('reservations.status', $accepted), ['status' => 'confirmed'])
            ->assertRedirect();
        $this->actingAs($superAdmin)
            ->patch(route('reservations.status', $rejected), ['status' => 'rejected'])
            ->assertRedirect();

        Notification::assertSentTo($customer, CustomerDecisionNotification::class, function (CustomerDecisionNotification $notification): bool {
            return $notification->subjectType === 'reservation'
                && $notification->identifier === 'KRM-ACCEPTED'
                && $notification->status === 'confirmed';
        });
        Notification::assertSentTo($customer, CustomerDecisionNotification::class, function (CustomerDecisionNotification $notification): bool {
            return $notification->subjectType === 'reservation'
                && $notification->identifier === 'KRM-REJECTED'
                && $notification->status === 'rejected';
        });
    }

    public function test_customer_is_emailed_when_orders_are_accepted_or_rejected(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $accepted = $this->order($customer);
        $rejected = $this->order($customer);

        $this->actingAs($cashier)
            ->patch(route('cashier.orders.confirm-payment', $accepted), ['cash_received' => 500])
            ->assertRedirect();
        $this->actingAs($cashier)
            ->patch(route('cashier.orders.reject', $rejected))
            ->assertRedirect();

        Notification::assertSentTo($customer, CustomerDecisionNotification::class, function (CustomerDecisionNotification $notification) use ($accepted): bool {
            return $notification->subjectType === 'order'
                && $notification->subjectId === $accepted->id
                && $notification->status === 'paid';
        });
        Notification::assertSentTo($customer, CustomerDecisionNotification::class, function (CustomerDecisionNotification $notification) use ($rejected): bool {
            return $notification->subjectType === 'order'
                && $notification->subjectId === $rejected->id
                && $notification->status === 'rejected';
        });
        $this->assertFalse(Schema::hasTable('notifications'));
    }

    public function test_decision_email_contains_the_status_and_customer_detail_link(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);
        $notification = new CustomerDecisionNotification(
            subjectType: 'order',
            subjectId: $order->id,
            identifier: '#'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            status: 'rejected',
        );
        $mail = $notification->toMail($customer);

        $this->assertSame("Order rejected | Kermit's", $mail->subject);
        $this->assertSame('View order', $mail->actionText);
        $this->assertSame(route('shop.orders.show', $order), $mail->actionUrl);
        $this->assertStringContainsString('has been rejected', implode(' ', $mail->introLines));
    }

    private function customer(): User
    {
        return User::factory()->create([
            'email' => 'customer.notifications@example.com',
            'role' => User::ROLE_CUSTOMER,
        ]);
    }

    private function reservation(User $customer, string $reference): Reservation
    {
        return Reservation::query()->create([
            'user_id' => $customer->id,
            'reference' => $reference,
            'type' => 'table',
            'table_size' => 2,
            'customer_name' => $customer->name,
            'email' => $customer->email,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay(),
            'guests' => 2,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);
    }

    private function order(User $customer): Order
    {
        return Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 500,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);
    }
}
