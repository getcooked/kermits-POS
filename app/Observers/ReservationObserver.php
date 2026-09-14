<?php

namespace App\Observers;

use App\Models\Reservation;
use App\Notifications\CustomerDecisionNotification;
use App\Services\ReservationPushNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ReservationObserver implements ShouldHandleEventsAfterCommit
{
    private const CUSTOMER_VISIBLE_FIELDS = [
        'status',
        'payment_status',
        'reservation_at',
        'reservation_end_at',
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
        $handledByOrderNotification = $reservation->order_id
            && $reservation->wasChanged('payment_status')
            && in_array($reservation->payment_status, ['paid', 'rejected'], true);

        if ($changedFields !== [] && ! $handledByOrderNotification) {
            $this->notifier->notify($reservation, $changedFields);
        }

        if (! $handledByOrderNotification
            && $reservation->wasChanged('status')
            && in_array($reservation->status, ['confirmed', 'rejected'], true)) {
            $customer = $reservation->user;
            if ($customer && ! $customer->trashed()) {
                $customer->notify(new CustomerDecisionNotification(
                    subjectType: 'reservation',
                    subjectId: (int) $reservation->id,
                    identifier: (string) $reservation->reference,
                    status: (string) $reservation->status,
                ));
            }
        }
    }
}
