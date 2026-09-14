<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CustomerStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $subjectType,
        private readonly int $subjectId,
        private readonly string $status,
        private readonly string $title,
        private readonly string $message,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string> */
    public function toArray(object $notifiable): array
    {
        return [
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'status' => $this->status,
            'title' => $this->title,
            'message' => $this->message,
        ];
    }
}
