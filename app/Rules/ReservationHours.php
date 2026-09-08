<?php

namespace App\Rules;

use App\Services\ReservationSchedule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ReservationHours implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(ReservationSchedule::class)->withinHours($value)) {
            $fail(ReservationSchedule::HOURS_MESSAGE);
        }
    }
}
