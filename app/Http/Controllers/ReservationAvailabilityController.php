<?php

namespace App\Http\Controllers;

use App\Services\ReservationSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReservationAvailabilityController extends Controller
{
    public function __invoke(Request $request, ReservationSchedule $schedules)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'type' => ['required', 'in:table,exclusive'],
            'guests' => ['required', 'integer', 'min:1', 'max:300'],
        ]);
        $slots = [];
        $start = CarbonImmutable::parse($data['date'].' '.config('reservations.opening_time'), config('app.timezone'));
        $last = $start->setTimeFromTimeString(config('reservations.last_start_time'));
        for ($at = $start; $at->lte($last); $at = $at->addMinutes(30)) {
            $end = CarbonImmutable::parse($schedules->endAt($at));
            $slots[] = [
                'start' => $at->format('Y-m-d\TH:i'), 'end' => $end->format('Y-m-d\TH:i'),
                'label' => $at->format('g:i A').' – '.$end->format('g:i A'),
                'available' => $at->gt(now()) && $schedules->isAvailable($at, $data['type'], $data['guests']),
            ];
        }

        return response()->json(['data' => $slots, 'timezone' => config('app.timezone'), 'hold_minutes' => config('reservations.hold_minutes')])
            ->header('Cache-Control', 'no-store');
    }
}
