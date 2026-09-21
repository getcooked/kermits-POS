<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerDecisionNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly string $identifier,
        public readonly string $status,
        public readonly bool $paymentOnly = false,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->paymentOnly) {
            return (new MailMessage)
                ->subject("Payment received | Kermit's")
                ->greeting("Hello {$notifiable->name},")
                ->line("PayMongo confirmed payment for your order {$this->identifier}.")
                ->line('Your reservation is awaiting approval. Open the order for its latest status.')
                ->action('View order', route('shop.orders.show', $this->subjectId))
                ->line('Thank you for choosing Kermit\'s.');
        }

        $accepted = in_array($this->status, ['confirmed', 'paid'], true);
        $type = ucfirst($this->subjectType);
        $decision = $accepted ? 'accepted' : 'rejected';
        $message = $this->subjectType === 'reservation'
            ? "Your reservation {$this->identifier} has been {$decision}."
            : "Your order {$this->identifier} has been {$decision}.";
        $url = $this->subjectType === 'reservation'
            ? route('reservations.show', $this->subjectId)
            : route('shop.orders.show', $this->subjectId);

        return (new MailMessage)
            ->subject("{$type} {$decision} | Kermit's")
            ->greeting("Hello {$notifiable->name},")
            ->line($message)
            ->line($accepted
                ? 'Kermit\'s has approved your request. Open the details below for the latest status.'
                : 'Kermit\'s could not approve your request. Open the details below to review its status.')
            ->action("View {$this->subjectType}", $url)
            ->line('Thank you for choosing Kermit\'s.');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60, 300, 900];
    }
}
