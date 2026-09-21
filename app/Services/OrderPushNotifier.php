<?php

namespace App\Services;

use App\Jobs\SendOrderUpdatedPush;
use App\Models\MobilePushInstallation;
use App\Models\Order;
use Illuminate\Support\Str;

class OrderPushNotifier
{
    public function notify(Order $order, ?string $title = null, ?string $body = null): void
    {
        if (! $order->customer_id) {
            return;
        }

        $installationIds = MobilePushInstallation::query()
            ->where('user_id', $order->customer_id)
            ->pluck('id');

        if ($installationIds->isEmpty()) {
            return;
        }

        [$defaultTitle, $defaultBody] = self::message($order);
        $eventId = (string) Str::uuid();

        foreach ($installationIds as $installationId) {
            SendOrderUpdatedPush::dispatch(
                installationId: (int) $installationId,
                orderId: (int) $order->id,
                eventId: $eventId,
                title: $title ?? $defaultTitle,
                body: $body ?? $defaultBody,
                status: (string) $order->payment_status,
            )->afterCommit();
        }
    }

    /** @return array{string, string} */
    public static function message(Order $order): array
    {
        $number = str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);

        return match ((string) $order->payment_status) {
            'paid' => $order->payment_method === 'paymongo'
                ? ['Payment received', "PayMongo confirmed payment for order #{$number}. Your reservation is awaiting approval."]
                : ['Order accepted', "Your order #{$number} was accepted and payment was confirmed."],
            'rejected' => ['Order rejected', "Your order #{$number} was rejected. Open the order for details."],
            default => ['Order updated', "Order #{$number} has a new status."],
        };
    }
}
