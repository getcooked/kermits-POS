<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MobilePayMongoTest extends TestCase
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

    public function test_mobile_checkout_starts_paymongo_and_returns_to_the_app_after_payment(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::response([
            'data' => ['id' => 'cs_mobile_123', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/mobile-session']],
        ])]);
        $customer = $this->customer();
        $product = Product::query()->create(['name' => 'App meal', 'price' => 175, 'stock' => 5, 'active' => true]);
        $token = $this->login($customer);

        $this->withToken($token)->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.paymongo_enabled', true);

        $order = $this->withToken($token)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'paymongo',
            'table_size' => 2,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->setTime(12, 0)->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('data.payment_method', 'paymongo')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.paymongo_checkout_url', 'https://checkout.paymongo.com/mobile-session')
            ->json('data');

        $returnUrl = route('mobile.paymongo.return', ['order' => $order['id']]);
        Http::assertSent(fn ($request) => $request['data']['attributes']['success_url'] === $returnUrl
            && $request['data']['attributes']['cancel_url'] === $returnUrl);
        $this->get($returnUrl)->assertOk()->assertSee('kermits://paymongo-return?order='.$order['id'], false);

        $this->withToken($token)->postJson('/api/v1/orders/'.$order['id'].'/paymongo')
            ->assertOk()->assertJsonPath('data.checkout_url', 'https://checkout.paymongo.com/mobile-session');
        Http::assertSentCount(1);

        $model = Order::query()->with('reservation')->findOrFail($order['id']);
        $this->sendWebhook($this->paidEvent($model, 50000))->assertOk();
        $this->withToken($token)->getJson('/api/v1/orders/'.$order['id'])->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.paymongo_checkout_url', null);
        $this->withToken($token)->postJson('/api/v1/orders/'.$order['id'].'/paymongo')->assertUnprocessable();
    }

    public function test_mobile_paymongo_requires_a_reservation_and_enabled_configuration(): void
    {
        Http::fake();
        $customer = $this->customer();
        $product = Product::query()->create(['name' => 'App meal', 'price' => 175, 'stock' => 5, 'active' => true]);
        $token = $this->login($customer);

        $this->withToken($token)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'paymongo',
        ])->assertUnprocessable()->assertJsonValidationErrors('table_size');

        config()->set('services.paymongo.secret_key', null);
        $this->withToken($token)->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.paymongo_enabled', false);
        $this->withToken($token)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'paymongo',
            'table_size' => 2,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->setTime(12, 0)->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(5, $product->fresh()->stock);
        Http::assertNothingSent();
    }

    public function test_mobile_order_saved_when_paymongo_is_down_can_retry_payment(): void
    {
        Http::fakeSequence('api.paymongo.com/*')
            ->push(['errors' => [['detail' => 'Unavailable']]], 500)
            ->push(['data' => ['id' => 'cs_retry_123', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/retry-session']]]);
        $customer = $this->customer();
        $product = Product::query()->create(['name' => 'App meal', 'price' => 175, 'stock' => 5, 'active' => true]);
        $token = $this->login($customer);

        $orderId = $this->withToken($token)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'paymongo',
            'table_size' => 2,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay()->setTime(12, 0)->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.paymongo_checkout_url', null)->json('data.id');

        $this->withToken($token)->postJson('/api/v1/orders/'.$orderId.'/paymongo')
            ->assertOk()->assertJsonPath('data.checkout_url', 'https://checkout.paymongo.com/retry-session');

        $other = $this->customer('09170000000');
        $this->withToken($this->login($other))->postJson('/api/v1/orders/'.$orderId.'/paymongo')->assertForbidden();
    }

    public function test_mobile_reservation_can_be_paid_through_its_linked_paymongo_order(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::response([
            'data' => ['id' => 'cs_reservation_123', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/reservation-session']],
        ])]);
        $customer = $this->customer();
        $token = $this->login($customer);

        $reservation = $this->withToken($token)->postJson('/api/v1/reservations', [
            'type' => 'table', 'table_size' => 4, 'phone' => '09171234567',
            'reservation_at' => now()->addDays(2)->setTime(12, 0)->toIso8601String(),
            'payment_method' => 'paymongo',
        ])->assertCreated()
            ->assertJsonPath('data.payment_method', 'paymongo')
            ->assertJsonPath('data.paymongo_checkout_url', 'https://checkout.paymongo.com/reservation-session')
            ->json('data');

        $order = Order::query()->findOrFail($reservation['order_id']);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame('paymongo', $order->payment_method);
        Http::assertSent(fn ($request) => $request['data']['attributes']['line_items'] === [
            ['name' => 'Table reservation', 'amount' => 25000, 'currency' => 'PHP', 'quantity' => 1],
        ]);

        $this->withToken($token)->getJson('/api/v1/reservations')->assertOk()
            ->assertJsonPath('data.0.order_id', $order->id)
            ->assertJsonPath('data.0.paymongo_checkout_url', 'https://checkout.paymongo.com/reservation-session');
        $this->assertSame($order->id, Reservation::query()->findOrFail($reservation['id'])->order_id);
    }

    private function customer(string $phone = '09171234567'): User
    {
        return User::factory()->create([
            'phone' => $phone, 'role' => User::ROLE_CUSTOMER, 'password' => 'MobilePassword123!',
        ]);
    }

    private function login(User $user): string
    {
        return $this->postJson('/api/v1/login', [
            'login' => $user->email, 'password' => 'MobilePassword123!', 'device_name' => 'Feature test',
        ])->assertOk()->json('data.token');
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
                        'id' => 'pay_mobile_123',
                        'attributes' => ['status' => 'paid', 'amount' => $amount, 'currency' => 'PHP'],
                    ]],
                ],
            ],
        ]];
    }

    private function sendWebhook(array $event): TestResponse
    {
        $body = json_encode($event);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_example');

        return $this->call('POST', '/api/paymongo/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Paymongo_Signature' => 't='.$timestamp.',te='.$signature.',li=',
        ], $body);
    }
}
