<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerPasswordResetCode extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Kermit's password reset code")
            ->greeting('Reset your password')
            ->line("Enter this six-digit code in the Kermit's app to choose a new password:")
            ->line($this->code)
            ->line('This code expires in 10 minutes. If you did not request it, you can ignore this email.');
    }
}
