<?php

namespace App\Observers;

use App\Models\Reservation;
use App\Services\ReservationPushNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ReservationObserver implements ShouldHandleEventsAfterCommit
{
    private const CUSTOMER_VISIBLE_FIELDS = [
        'status',
        'payment_status',
        'reservation_at',
        'type',
        'table_size',
        'guests',
        'food_request',
        'notes',
    ];

    public function __construct(private readonly ReservationPushNotifier $notifier) {}

    public function updated(Reservation $reservation): void
    {
        $changedFields = array_values(array_intersect(
            array_keys($reservation->getChanges()),
            self::CUSTOMER_VISIBLE_FIELDS,
        ));

        if ($changedFields !== []) {
            $this->notifier->notify($reservation, $changedFields);
        }
    }
}
