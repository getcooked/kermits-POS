<?php

namespace App\Rules;

use App\Models\Order;
use App\Models\Reservation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A GCash transaction reference can only pay for one order or reservation.
 */
class UniqueGcashReference implements ValidationRule
{
    public const MESSAGE = 'This GCash reference number has already been used. Please enter the reference from a new GCash payment.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $reference = trim((string) $value);
        if ($reference === '') {
            return;
        }

        if (Order::query()->where('payment_reference', $reference)->exists()
            || Reservation::query()->where('payment_reference', $reference)->exists()) {
            $fail(self::MESSAGE);
        }
    }
}
