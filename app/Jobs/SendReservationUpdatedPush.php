<?php

namespace App\Jobs;

use App\Contracts\FcmMessageSender;
use App\Models\MobilePushInstallation;
use App\Models\Reservation;
use App\Support\FcmSendResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendReservationUpdatedPush implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    /**
     * @param  list<string>  $changedFields
     */
    public function __construct(
        public readonly int $installationId,
        public readonly int $reservationId,
        public readonly string $eventId,
        public readonly string $title,
        public readonly string $body,
        public readonly string $status,
        public readonly string $paymentStatus,
        public readonly array $changedFields,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60, 300, 900];
    }

    public function handle(FcmMessageSender $sender): void
    {
        $installation = MobilePushInstallation::query()->find($this->installationId);
        $reservation = Reservation::query()->find($this->reservationId);

        if (! $installation || ! $reservation || $installation->user_id !== $reservation->user_id) {
            return;
        }

        $result = $sender->send((string) $installation->identifier, [
            'type' => 'reservation.updated',
            'event_id' => $this->eventId,
            'reservation_id' => (string) $reservation->id,
            'reference' => (string) $reservation->reference,
            'status' => $this->status,
            'payment_status' => $this->paymentStatus,
            'changed_fields' => implode(',', $this->changedFields),
            'title' => $this->title,
            'body' => $this->body,
            'user_id' => (string) $reservation->user_id,
        ]);

        if ($result === FcmSendResult::InvalidInstallation) {
            $installation->delete();
        }
    }
}
