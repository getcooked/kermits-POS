<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class ReservationSchedule
{
    /**
     * Store every reservation time in one canonical format so duplicate checks
     * work consistently for web datetime inputs and mobile ISO-8601 inputs.
     */
    public function normalize(string|DateTimeInterface $reservationAt): string
    {
        return CarbonImmutable::parse($reservationAt, config('app.timezone'))
            ->setTimezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }

    public function isAvailable(string|DateTimeInterface $reservationAt): bool
    {
        return ! Reservation::query()
            ->where('reservation_at', $this->normalize($reservationAt))
            ->exists();
    }
}
