<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Models\Reservation;
use App\Services\ReservationSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DiningTableController extends Controller
{
    public function index()
    {
        return view('tables.index', ['tables' => DiningTable::query()->orderBy('number')->get()]);
    }

    public function store(Request $request, ReservationSchedule $schedules)
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:9999', 'unique:dining_tables,number'],
            'capacity' => ['required', 'integer', 'min:1', 'max:300'],
        ]);
        DB::transaction(function () use ($schedules, $data) {
            $schedules->lock();
            DiningTable::query()->create($data);
        });

        return back()->with('status', 'Table added.');
    }

    public function update(Request $request, DiningTable $table, ReservationSchedule $schedules)
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:9999', Rule::unique('dining_tables', 'number')->ignore($table)],
            'capacity' => ['required', 'integer', 'min:1', 'max:300'],
            'active' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($schedules, $table, $data) {
            $schedules->lock();
            $bookings = $schedules->active()->where('dining_table_id', $table->id)
                ->where('reservation_end_at', '>', now());
            if ((! $data['active'] && (clone $bookings)->exists())
                || (clone $bookings)->where('guests', '>', $data['capacity'])->exists()) {
                throw ValidationException::withMessages(['table' => 'Reassign active reservations before disabling this table or reducing its capacity below their party size.']);
            }
            $table->update($data);
        });

        return back()->with('status', 'Table updated.');
    }

    public function reassign(Request $request, Reservation $reservation, ReservationSchedule $schedules)
    {
        $data = $request->validate(['dining_table_id' => ['required', 'integer', 'exists:dining_tables,id']]);
        $schedules->reassign($reservation, $data['dining_table_id'], $request->user()->id);

        return back()->with('status', 'Table assignment updated.');
    }
}
