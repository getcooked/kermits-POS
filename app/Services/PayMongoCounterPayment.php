<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Walk-in PayMongo payments at the cashier counter: a one-time QR Ph code the
 * customer scans with any bank or e-wallet app. The order is paid only after
 * PayMongo reports a matching payment (webhook or status check).
 */
class PayMongoCounterPayment
{
    public const QR_LIFETIME_MINUTES = 30;

    private const API = 'https://api.paymongo.com/v1';

    public static function enabled(): bool
    {
        return PayMongoCheckout::enabled();
    }

    public static function isCounterOrder(Order $order): bool
    {
        return $order->customer_id === null && $order->payment_method === 'paymongo';
    }

    /**
     * Creates the QR Ph code for a pending counter order and returns it as a data URI.
     */
    public function start(Order $order): string
    {
        if (! self::enabled()) {
            throw new RuntimeException('PayMongo is not configured.');
        }

        $amount = (int) round((float) $order->total * 100);
        $label = 'Kermit\'s POS order #'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);

        $intent = $this->client('kermits-counter-'.$order->id.'-intent')
            ->post(self::API.'/payment_intents', ['data' => ['attributes' => [
                'amount' => $amount,
                'currency' => 'PHP',
                'payment_method_allowed' => ['qrph'],
                'capture_type' => 'automatic',
                'description' => $label,
                'metadata' => ['order_id' => (string) $order->id],
            ]]]);
        $intentId = $intent->json('data.id');
        if (! $intent->successful() || ! is_string($intentId) || ! str_starts_with($intentId, 'pi_')) {
            throw new RuntimeException('PayMongo could not create the payment intent.');
        }

        $method = $this->client('kermits-counter-'.$order->id.'-method')
            ->post(self::API.'/payment_methods', ['data' => ['attributes' => ['type' => 'qrph']]]);
        $methodId = $method->json('data.id');
        if (! $method->successful() || ! is_string($methodId) || ! str_starts_with($methodId, 'pm_')) {
            throw new RuntimeException('PayMongo could not create the QR Ph payment method.');
        }

        $attached = $this->client('kermits-counter-'.$order->id.'-attach')
            ->post(self::API.'/payment_intents/'.$intentId.'/attach', ['data' => ['attributes' => [
                'payment_method' => $methodId,
            ]]]);
        $qr = $attached->json('data.attributes.next_action.code.image_url');
        if (! $attached->successful() || ! is_string($qr) || ! preg_match('#^data:image/(png|jpeg|svg\+xml);base64,[A-Za-z0-9+/=]+$#', $qr)) {
            throw new RuntimeException('PayMongo did not return a QR Ph code.');
        }

        $order->update(['paymongo_payment_intent_id' => $intentId]);
        Cache::put($this->qrCacheKey($order), $qr, now()->addMinutes(self::QR_LIFETIME_MINUTES));

        return $qr;
    }

    public function qrFor(Order $order): ?string
    {
        return Cache::get($this->qrCacheKey($order));
    }

    public function expiresAt(Order $order): CarbonInterface
    {
        return $order->created_at->copy()->addMinutes(self::QR_LIFETIME_MINUTES);
    }

    /**
     * Asks PayMongo whether the QR was paid (used when webhooks cannot reach this server).
     */
    public function refresh(Order $order): bool
    {
        if ($order->payment_status === 'paid') {
            return true;
        }
        if ($order->payment_status !== 'pending' || ! $order->paymongo_payment_intent_id || ! self::enabled()) {
            return false;
        }

        $response = $this->client()->get(self::API.'/payment_intents/'.$order->paymongo_payment_intent_id);
        if (! $response->successful() || $response->json('data.attributes.status') !== 'succeeded') {
            return false;
        }

        $payments = $response->json('data.attributes.payments', []);
        foreach (is_array($payments) ? $payments : [] as $payment) {
            if ($this->markPaid($order->paymongo_payment_intent_id, $payment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Marks the counter order paid when the PayMongo payment matches its total exactly.
     */
    public function markPaid(string $intentId, mixed $payment): bool
    {
        return DB::transaction(function () use ($intentId, $payment): bool {
            $order = Order::query()->where('paymongo_payment_intent_id', $intentId)->lockForUpdate()->first();
            if (! $order || ! self::isCounterOrder($order)) {
                return false;
            }
            if ($order->payment_status === 'paid') {
                return true;
            }

            $paymentId = data_get($payment, 'id');
            $matches = data_get($payment, 'attributes.status') === 'paid'
                && data_get($payment, 'attributes.currency') === 'PHP'
                && data_get($payment, 'attributes.amount') === (int) round((float) $order->total * 100)
                && is_string($paymentId) && str_starts_with($paymentId, 'pay_');
            if (! $matches) {
                return false;
            }

            if ($order->payment_status !== 'pending') {
                report(new RuntimeException("PayMongo payment {$paymentId} arrived for cancelled counter order #{$order->id}; refund it in the PayMongo dashboard."));

                return false;
            }

            $order->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'processed_by' => $order->user_id,
                'payment_reference' => $paymentId,
            ]);

            return true;
        }, attempts: 3);
    }

    /**
     * Cancels an unpaid counter QR and returns its items to stock. Checks PayMongo first so
     * a payment that already went through is never cancelled.
     */
    public function cancel(Order $order, User $by): bool
    {
        try {
            if ($this->refresh($order)) {
                return false;
            }
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['order' => 'PayMongo could not be reached to confirm this QR is unpaid. Try again in a moment.']);
        }

        DB::transaction(function () use ($order, $by): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! self::isCounterOrder($locked) || $locked->payment_status !== 'pending') {
                return;
            }

            $items = $locked->items()->get();
            $products = Product::query()->whereIn('id', $items->pluck('product_id')->unique()->sort()->values())
                ->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                if (! $product) {
                    continue;
                }
                $stockBefore = $product->stock;
                $product->update(['stock' => $stockBefore + $item->quantity]);
                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'user_id' => $by->id,
                    'type' => 'order_rejected',
                    'quantity' => $item->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockBefore + $item->quantity,
                    'note' => 'Stock returned from cancelled PayMongo QR sale #'.$locked->id,
                ]);
            }

            $locked->update(['payment_status' => 'rejected', 'processed_by' => $by->id]);
        }, attempts: 3);
        Cache::forget($this->qrCacheKey($order));

        return true;
    }

    private function client(?string $idempotencyKey = null): PendingRequest
    {
        $client = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
            ->acceptJson()->timeout(15)->connectTimeout(5);

        return $idempotencyKey ? $client->withHeaders(['Idempotency-Key' => $idempotencyKey]) : $client;
    }

    private function qrCacheKey(Order $order): string
    {
        return 'paymongo-counter-qr:'.$order->id;
    }
}
