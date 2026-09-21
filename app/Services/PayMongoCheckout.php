<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PayMongoCheckout
{
    public static function enabled(): bool
    {
        return config('services.paymongo.enabled')
            && filled(config('services.paymongo.secret_key'))
            && filled(config('services.paymongo.webhook_secret'))
            && config('services.paymongo.payment_methods') !== [];
    }

    public function urlFor(Order $order): string
    {
        if (! self::enabled()) {
            throw new RuntimeException('PayMongo is not configured.');
        }

        return DB::transaction(function () use ($order): string {
            $locked = Order::query()->with(['items.product', 'reservation'])
                ->lockForUpdate()->findOrFail($order->id);

            if ($locked->payment_method !== 'paymongo' || $locked->payment_status !== 'pending') {
                throw ValidationException::withMessages(['payment_method' => 'This order is not awaiting a PayMongo payment.']);
            }
            if (! $locked->reservation || ! in_array($locked->reservation->booking_status, ['pending', 'confirmed'], true)) {
                throw ValidationException::withMessages(['reservation_at' => 'This reservation is no longer available for payment.']);
            }
            if ($locked->paymongo_checkout_id && $locked->paymongo_checkout_url) {
                return $locked->paymongo_checkout_url;
            }

            $lineItems = $locked->items->map(fn ($item): array => [
                'name' => mb_substr($item->product?->name ?? 'Menu item', 0, 100),
                'amount' => (int) round((float) $item->unit_price * 100),
                'currency' => 'PHP',
                'quantity' => (int) $item->quantity,
            ])->all();
            $lineItems[] = [
                'name' => 'Table reservation',
                'amount' => (int) round((float) $locked->reservation->total_amount * 100),
                'currency' => 'PHP',
                'quantity' => 1,
            ];

            $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
                ->withHeaders(['Idempotency-Key' => 'kermits-order-'.$locked->id.'-checkout'])
                ->acceptJson()->timeout(15)->connectTimeout(5)
                ->post('https://api.paymongo.com/v2/checkout_sessions', [
                    'data' => ['attributes' => [
                        'line_items' => $lineItems,
                        'payment_method_types' => array_values(config('services.paymongo.payment_methods')),
                        'success_url' => route('shop.orders.show', $locked),
                        'cancel_url' => route('shop.orders.show', $locked),
                        'reference_number' => $locked->reservation->reference,
                    ]],
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('PayMongo could not start checkout.');
            }
            $id = $response->json('data.id');
            $url = $response->json('data.attributes.checkout_url');
            if (! is_string($id) || ! str_starts_with($id, 'cs_')
                || ! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https'
                || parse_url($url, PHP_URL_HOST) !== 'checkout.paymongo.com') {
                throw new RuntimeException('PayMongo returned an invalid checkout session.');
            }

            $locked->update(['paymongo_checkout_id' => $id, 'paymongo_checkout_url' => $url]);

            return $url;
        }, attempts: 3);
    }
}
