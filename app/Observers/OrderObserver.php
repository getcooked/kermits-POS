<?php

namespace App\Observers;

use App\Models\Order;
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
    }
}
