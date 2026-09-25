<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.paymongo.webhook_secret');
        $key = config('services.paymongo.secret_key');
        if (! config('services.paymongo.enabled') || ! is_string($secret) || $secret === '' || ! is_string($key) || $key === '') {
            return response()->json(['error' => 'Webhook unavailable'], 503);
        }

        $parts = [];
        foreach (explode(',', (string) $request->header('Paymongo-Signature')) as $part) {
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$name] = $value;
        }
        $timestamp = $parts['t'] ?? '';
        $mode = str_starts_with($key, 'sk_live_') ? 'li' : 'te';
        $signature = $parts[$mode] ?? '';
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300
            || ! preg_match('/^[a-f0-9]{64}$/i', $signature)
            || ! hash_equals(hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret), strtolower($signature))) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();
        if (data_get($payload, 'data.type') !== 'checkout_session.payment.paid') {
            return response()->json(['received' => true]);
        }
        if (data_get($payload, 'data.livemode') !== ($mode === 'li')) {
            return response()->json(['error' => 'Payment mode mismatch'], 422);
        }

        $sessionId = data_get($payload, 'data.data.id');
        if (! is_string($sessionId) || $sessionId === '') {
            return response()->json(['error' => 'Missing checkout session'], 422);
        }

        return DB::transaction(function () use ($payload, $sessionId): JsonResponse {
            $order = Order::query()->with('reservation')->where('paymongo_checkout_id', $sessionId)
                ->lockForUpdate()->first();
            if (! $order || $order->payment_method !== 'paymongo' || ! $order->reservation) {
                return response()->json(['error' => 'Unknown checkout session'], 422);
            }
            if ($order->payment_status === 'paid') {
                return response()->json(['received' => true]);
            }
            if ($order->payment_status !== 'pending'
                || data_get($payload, 'data.data.attributes.reference_number') !== $order->reservation->reference) {
                return response()->json(['error' => 'Order mismatch'], 422);
            }

            $due = (int) round($order->totalDue() * 100);
            $payments = data_get($payload, 'data.data.attributes.payments', []);
            $paid = collect(is_array($payments) ? $payments : [])->first(fn ($payment): bool => data_get($payment, 'attributes.status') === 'paid'
                && data_get($payment, 'attributes.currency') === 'PHP'
                && data_get($payment, 'attributes.amount') === $due);
            if (! $paid) {
                return response()->json(['error' => 'Payment amount mismatch'], 422);
            }

            $order->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'processed_by' => null,
                'payment_reference' => data_get($paid, 'id'),
            ]);
            $updates = ['payment_status' => 'paid'];
            if (Schema::hasColumn('reservations', 'hold_expires_at')) {
                $updates['hold_expires_at'] = null;
            }
            $order->reservation->update($updates);

            return response()->json(['received' => true]);
        }, attempts: 3);
    }
}
