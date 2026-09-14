<?php

namespace Tests\Feature;

use App\Contracts\FcmMessageSender;
use App\Jobs\SendOrderUpdatedPush;
use App\Models\MobileApiToken;
use App\Models\MobilePushInstallation;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Observers\ReservationObserver;
use App\Support\FcmSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_acceptance_creates_a_customer_alert(): void
    {
        $customer = $this->customer();
        $reservation = $this->reservation($customer);

        $reservation->status = 'confirmed';
        $reservation->syncChanges();
        app(ReservationObserver::class)->updated($reservation);

        $notification = $customer->notifications()->sole();
        $this->assertSame('reservation', $notification->data['subject_type']);
        $this->assertSame($reservation->id, $notification->data['subject_id']);
        $this->assertSame('confirmed', $notification->data['status']);
        $this->assertSame('Reservation accepted', $notification->data['title']);
    }

    public function test_order_acceptance_creates_an_alert_and_queues_a_mobile_push(): void
    {
        Queue::fake();
        $customer = $this->customer();
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 500,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);
        $installation = $this->installation($customer);

        $order->payment_status = 'paid';
        $order->syncChanges();
        app(OrderObserver::class)->updated($order);

        $notification = $customer->notifications()->sole();
        $this->assertSame('order', $notification->data['subject_type']);
        $this->assertSame('paid', $notification->data['status']);
        $this->assertSame('Order accepted', $notification->data['title']);

        Queue::assertPushed(SendOrderUpdatedPush::class, function (SendOrderUpdatedPush $job) use ($installation, $order): bool {
            return $job->installationId === $installation->id
                && $job->orderId === $order->id
                && $job->status === 'paid'
                && $job->title === 'Order accepted';
        });
    }

    public function test_order_rejection_push_contains_only_customer_safe_data(): void
    {
        $customer = $this->customer();
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 500,
            'payment_method' => 'cash',
            'payment_status' => 'rejected',
        ]);
        $installation = $this->installation($customer);
        $sender = new class implements FcmMessageSender
        {
            /** @var array<string, string> */
            public array $data = [];

            public function send(string $installationId, array $data): FcmSendResult
            {
                $this->data = $data;

                return FcmSendResult::Sent;
            }
        };

        (new SendOrderUpdatedPush(
            installationId: $installation->id,
            orderId: $order->id,
            eventId: 'order-event',
            title: 'Order rejected',
            body: 'Your order was rejected.',
            status: 'rejected',
        ))->handle($sender);

        $this->assertSame('order.updated', $sender->data['type']);
        $this->assertSame((string) $order->id, $sender->data['order_id']);
        $this->assertSame('rejected', $sender->data['status']);
        $this->assertSame((string) $customer->id, $sender->data['user_id']);
        $this->assertArrayNotHasKey('email', $sender->data);
        $this->assertArrayNotHasKey('phone', $sender->data);
    }

    public function test_customer_can_open_and_mark_their_notification_as_read(): void
    {
        $customer = $this->customer();
        $reservation = $this->reservation($customer);
        $reservation->status = 'rejected';
        $reservation->syncChanges();
        app(ReservationObserver::class)->updated($reservation);
        $notification = $customer->notifications()->sole();

        $this->actingAs($customer)->get(route('customer.notifications.index'))
            ->assertOk()
            ->assertSee('Reservation rejected')
            ->assertSee('New');

        $this->actingAs($customer)
            ->patch(route('customer.notifications.read', $notification))
            ->assertRedirect(route('reservations.show', $reservation));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_customer_cannot_read_another_customers_notification(): void
    {
        $owner = $this->customer('owner@example.com');
        $other = $this->customer('other@example.com');
        $reservation = $this->reservation($owner);
        $reservation->status = 'confirmed';
        $reservation->syncChanges();
        app(ReservationObserver::class)->updated($reservation);

        $this->actingAs($other)
            ->patch(route('customer.notifications.read', $owner->notifications()->sole()))
            ->assertForbidden();
    }

    private function customer(string $email = 'alerts@example.com'): User
    {
        return User::factory()->create([
            'email' => $email,
            'role' => User::ROLE_CUSTOMER,
        ]);
    }

    private function reservation(User $customer): Reservation
    {
        return Reservation::query()->create([
            'user_id' => $customer->id,
            'reference' => 'KRM-ALERT-'.$customer->id,
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

    private function installation(User $customer): MobilePushInstallation
    {
        $token = MobileApiToken::query()->create([
            'user_id' => $customer->id,
            'name' => 'Notification test',
            'token_hash' => hash('sha256', 'notification-test-'.$customer->id),
            'expires_at' => now()->addDay(),
        ]);

        return MobilePushInstallation::query()->create([
            'user_id' => $customer->id,
            'mobile_api_token_id' => $token->id,
            'provider' => 'fcm',
            'identifier_kind' => 'fid',
            'identifier' => 'notification-installation-'.$customer->id,
            'identifier_hash' => hash('sha256', 'notification-installation-'.$customer->id),
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);
    }
}
