<?php

namespace App\Http\Controllers;

use App\Services\ReservationSchedule;
use App\Services\TableLayout;
use Illuminate\Http\Request;

class ReservationAvailabilityController extends Controller
{
    public function __invoke(Request $request, ReservationSchedule $schedules, TableLayout $tables)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'type' => ['required', 'in:table,exclusive'],
            'guests' => ['required', 'integer', 'min:1', 'max:300'],
            'table' => ['exclude_unless:type,table', ...$tables->requestRules()],
        ]);
        $slots = $schedules->availabilityForDate(
            $data['date'],
            $data['type'],
            (int) $data['guests'],
            isset($data['table']) ? (int) $data['table'] : null,
        );

        return response()->json(['data' => $slots, 'timezone' => config('app.timezone'), 'hold_minutes' => config('reservations.hold_minutes')])
            ->header('Cache-Control', 'no-store');
    }
}
