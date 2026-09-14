<?php

namespace App\Jobs;

use App\Contracts\FcmMessageSender;
use App\Models\MobilePushInstallation;
use App\Models\Order;
use App\Support\FcmSendResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderUpdatedPush implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(
        public readonly int $installationId,
        public readonly int $orderId,
        public readonly string $eventId,
        public readonly string $title,
        public readonly string $body,
        public readonly string $status,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60, 300, 900];
    }

    public function handle(FcmMessageSender $sender): void
    {
        $installation = MobilePushInstallation::query()->find($this->installationId);
        $order = Order::query()->find($this->orderId);

        if (! $installation || ! $order || $installation->user_id !== $order->customer_id) {
            return;
        }

        $result = $sender->send((string) $installation->identifier, [
            'type' => 'order.updated',
            'event_id' => $this->eventId,
            'order_id' => (string) $order->id,
            'status' => $this->status,
            'title' => $this->title,
            'body' => $this->body,
            'user_id' => (string) $order->customer_id,
        ]);

        if ($result === FcmSendResult::InvalidInstallation) {
            $installation->delete();
        }
    }
}
