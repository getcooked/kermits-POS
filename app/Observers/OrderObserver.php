<?php

namespace App\Observers;

use App\Models\Order;
use App\Notifications\CustomerStatusNotification;
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

        $customer = $order->customer;
        if (! $customer) {
            return;
        }

        [$title, $message] = OrderPushNotifier::message($order);

        $customer->notify(new CustomerStatusNotification(
            subjectType: 'order',
            subjectId: (int) $order->id,
            status: (string) $order->payment_status,
            title: $title,
            message: $message,
        ));
        $this->pushNotifier->notify($order, $title, $message);
    }
}
