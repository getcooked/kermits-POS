<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PayMongoCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config()->set('services.paymongo.enabled', true);
        config()->set('services.paymongo.secret_key', 'sk_test_example');
        config()->set('services.paymongo.webhook_secret', 'whsec_example');
        config()->set('services.paymongo.payment_methods', ['gcash', 'qrph']);
    }

    public function test_checkout_uses_server_prices_and_only_a_signed_matching_payment_marks_the_order_paid(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::response([
            'data' => ['id' => 'cs_test_123', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/test-session']],
        ])]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $product = Product::query()->create(['name' => 'Test meal', 'price' => 175, 'stock' => 5, 'active' => true]);

        $this->actingAs($customer)->post(route('shop.orders.store'), [
            'quantities' => [$product->id => 2],
            'table_size' => 2,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->setTime(12, 0)->format('Y-m-d\TH:i'),
            'payment_method' => 'paymongo',
        ])->assertRedirect('https://checkout.paymongo.com/test-session');

        $order = Order::query()->with('reservation')->firstOrFail();
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('cs_test_123', $order->paymongo_checkout_id);
        $this->assertSame(3, $product->fresh()->stock);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.paymongo.com/v2/checkout_sessions'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_example:'))
            && $request['data']['attributes']['line_items'] === [
                ['name' => 'Test meal', 'amount' => 17500, 'currency' => 'PHP', 'quantity' => 2],
                ['name' => 'Table reservation', 'amount' => 15000, 'currency' => 'PHP', 'quantity' => 1],
            ]);

        $this->get(route('shop.orders.show', $order))->assertOk()->assertSee('Continue to PayMongo');
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->post(route('shop.orders.paymongo', $order))->assertRedirect('https://checkout.paymongo.com/test-session');
        Http::assertSentCount(1);

        $event = $this->paidEvent($order, 50000);
        $this->sendWebhook($event, 'bad')->assertUnauthorized();
        $this->sendWebhook($event)->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('pay_test_123', $order->fresh()->payment_reference);
        $this->assertSame('paid', $order->reservation->fresh()->payment_status);
        $this->sendWebhook($event)->assertOk();
        $this->assertSame(3, $product->fresh()->stock);
        $this->get(route('customer.notifications'))->assertOk()
            ->assertSee('payment received')->assertSee('awaiting approval');

        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($superAdmin)->get('/reports?payment_method=paymongo')
            ->assertOk()->assertSee('PayMongo · 1 sales');
    }

    public function test_wrong_amount_cannot_mark_an_order_paid(): void
    {
        $order = $this->pendingOrder();

        $this->sendWebhook($this->paidEvent($order, 1))->assertStatus(422);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_cashier_cannot_manually_confirm_or_reject_an_active_checkout(): void
    {
        $order = $this->pendingOrder();
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $this->actingAs($cashier)->patch(route('cashier.orders.confirm-payment', $order), [
            'cash_received' => 500,
        ])->assertSessionHasErrors('order');
        $this->actingAs($cashier)->patch(route('cashier.orders.reject', $order))
            ->assertSessionHasErrors('order');
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_failed_checkout_can_be_rejected_and_releases_reserved_stock(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::response(['errors' => [['detail' => 'Unavailable']]], 503)]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create(['name' => 'Meal', 'price' => 100, 'stock' => 5, 'active' => true]);

        $this->actingAs($customer)->post(route('shop.orders.store'), [
            'quantities' => [$product->id => 1],
            'table_size' => 2,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->setTime(12, 0)->format('Y-m-d\TH:i'),
            'payment_method' => 'paymongo',
        ])->assertRedirect(route('shop.orders.show', 1));

        $order = Order::query()->firstOrFail();
        $this->assertNull($order->paymongo_checkout_id);
        $this->assertSame(4, $product->fresh()->stock);
        $this->actingAs($cashier)->get(route('cashier.orders.index'))->assertSee('PayMongo checkout failed');
        $this->patch(route('cashier.orders.reject', $order))->assertRedirect();
        $this->assertSame('rejected', $order->fresh()->payment_status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_checkout_is_unavailable_without_configuration_and_only_owner_can_retry(): void
    {
        config()->set('services.paymongo.enabled', false);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $other = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $product = Product::query()->create(['name' => 'Meal', 'price' => 100, 'stock' => 5, 'active' => true]);

        $this->actingAs($customer)->post(route('shop.orders.store'), [
            'quantities' => [$product->id => 1],
            'table_size' => 2,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->setTime(12, 0)->format('Y-m-d\TH:i'),
            'payment_method' => 'paymongo',
        ])->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);

        $order = $this->pendingOrder();
        $this->actingAs($other)->post(route('shop.orders.paymongo', $order))->assertForbidden();
    }

    private function pendingOrder(): Order
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'customer_id' => $customer->id,
            'total' => 350,
            'payment_method' => 'paymongo',
            'payment_status' => 'pending',
            'paymongo_checkout_id' => 'cs_test_123',
            'paymongo_checkout_url' => 'https://checkout.paymongo.com/test-session',
        ]);
        $order->reservation()->create([
            'user_id' => $customer->id,
            'reference' => 'KRM-TEST-123',
            'type' => 'table',
            'table_size' => 2,
            'customer_name' => $customer->name,
            'email' => $customer->email,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->setTime(12, 0),
            'guests' => 2,
            'reservation_fee' => 150,
            'food_total' => 0,
            'total_amount' => 150,
            'payment_method' => 'paymongo',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        return $order->load('reservation');
    }

    private function paidEvent(Order $order, int $amount): array
    {
        return ['data' => [
            'type' => 'checkout_session.payment.paid',
            'livemode' => false,
            'data' => [
                'id' => $order->paymongo_checkout_id,
                'attributes' => [
                    'reference_number' => $order->reservation->reference,
                    'payments' => [[
                        'id' => 'pay_test_123',
                        'attributes' => ['status' => 'paid', 'amount' => $amount, 'currency' => 'PHP'],
                    ]],
                ],
            ],
        ]];
    }

    private function sendWebhook(array $event, ?string $signature = null): TestResponse
    {
        $body = json_encode($event);
        $timestamp = (string) time();
        $signature ??= hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_example');

        return $this->call('POST', '/api/paymongo/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Paymongo_Signature' => 't='.$timestamp.',te='.$signature.',li=',
        ], $body);
    }
}
