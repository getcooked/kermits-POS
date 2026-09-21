<?php

namespace App\Observers;

use App\Models\Order;
use App\Notifications\CustomerDecisionNotification;
use App\Services\OrderPushNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class OrderObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly OrderPushNotifier $pushNotifier) {}

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('payment_status') || ! in_array($order->payment_status, ['paid', 'rejected'], true)) {
            return;
        }

        if (! $order->customer_id) {
            return;
        }

        [$title, $message] = OrderPushNotifier::message($order);
        $this->pushNotifier->notify($order, $title, $message);

        $customer = $order->customer;
        if ($customer && ! $customer->trashed()) {
            $customer->notify(new CustomerDecisionNotification(
                subjectType: 'order',
                subjectId: (int) $order->id,
                identifier: '#'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
                status: (string) $order->payment_status,
                paymentOnly: $order->payment_method === 'paymongo' && $order->payment_status === 'paid',
            ));
        }
    }
}
