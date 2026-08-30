<?php

namespace App\Services;

use App\Jobs\SendReservationUpdatedPush;
use App\Models\MobilePushInstallation;
use App\Models\Reservation;
use Illuminate\Support\Str;

class ReservationPushNotifier
{
    /**
     * @param  list<string>  $changedFields
     */
    public function notify(Reservation $reservation, array $changedFields): void
    {
        if (! $reservation->user_id) {
            return;
        }

        $installationIds = MobilePushInstallation::query()
            ->where('user_id', $reservation->user_id)
            ->pluck('id');

        if ($installationIds->isEmpty()) {
            return;
        }

        [$title, $body] = $this->message($reservation, $changedFields);
        $eventId = (string) Str::uuid();

        foreach ($installationIds as $installationId) {
            SendReservationUpdatedPush::dispatch(
                installationId: (int) $installationId,
                reservationId: (int) $reservation->id,
                eventId: $eventId,
                title: $title,
                body: $body,
                status: (string) $reservation->status,
                paymentStatus: (string) $reservation->payment_status,
                changedFields: $changedFields,
            )->afterCommit();
        }
    }

    /**
     * @param  list<string>  $changedFields
     * @return array{string, string}
     */
    private function message(Reservation $reservation, array $changedFields): array
    {
        $reference = (string) $reservation->reference;

        if (in_array('status', $changedFields, true)) {
            $status = ucfirst((string) $reservation->status);

            return ["Reservation {$status}", "Reservation {$reference} is now ".strtolower($status).'.'];
        }

        if (in_array('payment_status', $changedFields, true)) {
            $status = strtolower((string) $reservation->payment_status);

            return ['Payment update', "Payment for reservation {$reference} is now {$status}."];
        }

        if (in_array('reservation_at', $changedFields, true)) {
            return ['Schedule updated', "The schedule for reservation {$reference} was updated."];
        }

        if (in_array('items', $changedFields, true)) {
            return ['Reservation order updated', "The order for reservation {$reference} was updated."];
        }

        return ['Reservation updated', "Reservation {$reference} has new details."];
    }
}
