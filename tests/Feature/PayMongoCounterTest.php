<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PayMongoCounterTest extends TestCase
{
    use RefreshDatabase;

    private const QR = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.paymongo.enabled', true);
        config()->set('services.paymongo.secret_key', 'sk_test_example');
        config()->set('services.paymongo.webhook_secret', 'whsec_example');
        config()->set('services.paymongo.payment_methods', ['gcash', 'qrph']);
    }

    public function test_cashier_shows_a_qr_and_the_webhook_completes_the_sale(): void
    {
        $this->fakePayMongo();
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create(['name' => 'Counter meal', 'price' => 120, 'stock' => 5, 'active' => true]);

        $this->actingAs($cashier)->get(route('cashier'))->assertOk()->assertSee('PayMongo QR Ph')->assertSee('value="paymongo"', false);

        $response = $this->actingAs($cashier)->post(route('cashier.checkout'), [
            'quantities' => [$product->id => 2],
            'payment_method' => 'paymongo',
        ]);
        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('cashier.paymongo.show', $order));

        $this->assertSame('pending', $order->payment_status);
        $this->assertNull($order->customer_id);
        $this->assertSame('pi_test_1', $order->paymongo_payment_intent_id);
        $this->assertSame(3, $product->fresh()->stock);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.paymongo.com/v1/payment_intents'
            && $request['data']['attributes']['amount'] === 24000
            && $request['data']['attributes']['payment_method_allowed'] === ['qrph']);

        $this->get(route('cashier.paymongo.show', $order))
            ->assertOk()
            ->assertSee(self::QR, false)
            ->assertSee('240.00');
        $this->getJson(route('cashier.paymongo.status', $order))->assertOk()->assertJson(['status' => 'pending']);

        $this->sendWebhook($this->paymentPaidEvent('pi_test_1', 100))->assertOk();
        $this->assertSame('pending', $order->fresh()->payment_status);

        $this->sendWebhook($this->paymentPaidEvent('pi_test_1', 24000))->assertOk();
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('pay_counter_1', $order->payment_reference);
        $this->assertSame($cashier->id, $order->processed_by);
        $this->assertNotNull($order->paid_at);

        $this->getJson(route('cashier.paymongo.status', $order))
            ->assertOk()
            ->assertJson(['status' => 'paid', 'receipt_url' => route('receipts.show', $order)]);
        $this->get(route('cashier.paymongo.show', $order))->assertRedirect(route('receipts.show', $order));
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_status_check_confirms_payment_directly_with_paymongo_when_no_webhook_arrives(): void
    {
        $this->fakePayMongo(intentStatus: 'succeeded', paidAmount: 12000);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create(['name' => 'Counter meal', 'price' => 120, 'stock' => 5, 'active' => true]);

        $this->actingAs($cashier)->post(route('cashier.checkout'), [
            'quantities' => [$product->id => 1],
            'payment_method' => 'paymongo',
        ]);
        $order = Order::query()->firstOrFail();

        $this->getJson(route('cashier.paymongo.status', $order))->assertOk()->assertJson(['status' => 'paid']);
        $this->assertSame('pay_counter_1', $order->fresh()->payment_reference);
    }

    public function test_cancelling_an_unpaid_qr_returns_stock_and_a_late_payment_does_not_reopen_it(): void
    {
        $this->fakePayMongo();
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create(['name' => 'Counter meal', 'price' => 120, 'stock' => 5, 'active' => true]);

        $this->actingAs($cashier)->post(route('cashier.checkout'), ['quantities' => [$product->id => 2], 'payment_method' => 'paymongo']);
        $order = Order::query()->firstOrFail();

        $this->post(route('cashier.paymongo.cancel', $order))->assertRedirect(route('cashier'));
        $this->assertSame('rejected', $order->fresh()->payment_status);
        $this->assertSame(5, $product->fresh()->stock);

        $this->sendWebhook($this->paymentPaidEvent('pi_test_1', 24000))->assertOk();
        $this->assertSame('rejected', $order->fresh()->payment_status);
    }

    public function test_cancelling_a_qr_that_was_already_paid_completes_the_sale_instead(): void
    {
        $this->fakePayMongo(intentStatus: 'succeeded', paidAmount: 12000);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create(['name' => 'Counter meal', 'price' => 120, 'stock' => 5, 'active' => true]);

        $this->actingAs($cashier)->post(route('cashier.checkout'), ['quantities' => [$product->id => 1], 'payment_method' => 'paymongo']);
        $order = Order::query()->firstOrFail();

        $this->post(route('cashier.paymongo.cancel', $order))->assertRedirect(route('receipts.show', $order));
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(4, $product->fresh()->stock);
    }

    public function test_failed_qr_creation_releases_stock_and_returns_to_the_pos(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::response(['errors' => [['detail' => 'Unavailable']]], 503)]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create(['name' => 'Counter meal', 'price' => 120, 'stock' => 5, 'active' => true]);

        $this->actingAs($cashier)->from(route('cashier'))->post(route('cashier.checkout'), [
            'quantities' => [$product->id => 2],
            'payment_method' => 'paymongo',
        ])->assertRedirect(route('cashier'))->assertSessionHasErrors('payment_method');

        $this->assertSame('rejected', Order::query()->firstOrFail()->payment_status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_expired_qr_sales_are_released_when_the_pos_opens(): void
    {
        $this->fakePayMongo();
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create(['name' => 'Counter meal', 'price' => 120, 'stock' => 5, 'active' => true]);

        $this->actingAs($cashier)->post(route('cashier.checkout'), ['quantities' => [$product->id => 2], 'payment_method' => 'paymongo']);
        $order = Order::query()->firstOrFail();

        $this->travel(31)->minutes();
        $this->get(route('cashier'))->assertOk();

        $this->assertSame('rejected', $order->fresh()->payment_status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_paymongo_is_hidden_and_rejected_when_not_configured(): void
    {
        config()->set('services.paymongo.enabled', false);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $product = Product::query()->create(['name' => 'Counter meal', 'price' => 120, 'stock' => 5, 'active' => true]);

        $this->actingAs($cashier)->get(route('cashier'))->assertOk()->assertDontSee('value="paymongo"', false);
        $this->post(route('cashier.checkout'), ['quantities' => [$product->id => 1], 'payment_method' => 'paymongo'])
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_only_staff_can_view_counter_qr_pages_and_customer_orders_are_not_counter_sales(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $counterOrder = Order::query()->create([
            'user_id' => $cashier->id, 'total' => 100, 'payment_method' => 'paymongo', 'payment_status' => 'pending',
        ]);
        $onlineOrder = Order::query()->create([
            'user_id' => $customer->id, 'customer_id' => $customer->id, 'total' => 100,
            'payment_method' => 'paymongo', 'payment_status' => 'pending',
        ]);

        $this->actingAs($customer)->get(route('cashier.paymongo.show', $counterOrder))->assertForbidden();
        $this->actingAs($customer)->post(route('cashier.paymongo.cancel', $counterOrder))->assertForbidden();
        $this->actingAs($cashier)->get(route('cashier.paymongo.show', $onlineOrder))->assertNotFound();
        $this->actingAs($cashier)->post(route('cashier.paymongo.cancel', $onlineOrder))->assertNotFound();
    }

    private function fakePayMongo(string $intentId = 'pi_test_1', string $intentStatus = 'awaiting_next_action', int $paidAmount = 0): void
    {
        $payments = $intentStatus === 'succeeded'
            ? [['id' => 'pay_counter_1', 'type' => 'payment', 'attributes' => ['status' => 'paid', 'amount' => $paidAmount, 'currency' => 'PHP']]]
            : [];

        Http::fake([
            "api.paymongo.com/v1/payment_intents/{$intentId}/attach" => Http::response(['data' => ['id' => $intentId, 'attributes' => [
                'status' => 'awaiting_next_action',
                'next_action' => ['type' => 'consume_qr', 'code' => ['image_url' => self::QR]],
            ]]]),
            "api.paymongo.com/v1/payment_intents/{$intentId}" => Http::response(['data' => ['id' => $intentId, 'attributes' => [
                'status' => $intentStatus,
                'payments' => $payments,
            ]]]),
            'api.paymongo.com/v1/payment_intents' => Http::response(['data' => ['id' => $intentId, 'attributes' => ['status' => 'awaiting_payment_method']]]),
            'api.paymongo.com/v1/payment_methods' => Http::response(['data' => ['id' => 'pm_test_1', 'attributes' => ['type' => 'qrph']]]),
        ]);
    }

    /** PayMongo's real event envelope: data.attributes.{type,livemode,data}. */
    private function paymentPaidEvent(string $intentId, int $amount): array
    {
        return ['data' => ['id' => 'evt_test_1', 'type' => 'event', 'attributes' => [
            'type' => 'payment.paid',
            'livemode' => false,
            'data' => ['id' => 'pay_counter_1', 'type' => 'payment', 'attributes' => [
                'amount' => $amount,
                'currency' => 'PHP',
                'status' => 'paid',
                'payment_intent_id' => $intentId,
            ]],
        ]]];
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
