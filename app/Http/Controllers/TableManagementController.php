<?php

namespace App\Http\Controllers;

use App\Http\Requests\SeatTableRequest;
use App\Http\Requests\UpdateTableLayoutRequest;
use App\Http\Requests\UpdateTableManagementRequest;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationPricing;
use App\Services\ReservationSchedule;
use App\Services\TableFloor;
use App\Services\TableLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TableManagementController extends Controller
{
    public function index(ReservationPricing $pricing, TableLayout $layout, TableFloor $floor, ReservationSchedule $schedules): View
    {
        // Tables with a live booking (not yet seated, not past the late window) or guests sitting at them now.
        $bookedTableIds = $layout->hasFloorStatus()
            ? $schedules->active()
                ->where('type', 'table')
                ->whereNotNull('dining_table_id')
                ->whereNull('seated_at')
                ->where('reservation_at', '>=', now()->subMinutes(TableFloor::LATE_MINUTES))
                ->distinct()
                ->pluck('dining_table_id')
                ->merge(DiningTable::query()->whereNotNull('occupied_at')->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all()
            : [];

        return view('tables.index', [
            'bookedTableIds' => $bookedTableIds,
            'tableFees' => $pricing->tableFees(),
            'maxGuests' => $pricing->maxGuests(),
            'maxTables' => UpdateTableManagementRequest::MAX_TABLES,
            'diningTables' => DiningTable::query()->withCount('reservations')->orderBy('number')->get(),
            'maxDiningTables' => UpdateTableLayoutRequest::MAX_TABLES,
            'maxSeats' => UpdateTableLayoutRequest::MAX_SEATS,
            'turnoverMinutes' => $layout->turnoverMinutes(),
            'maxTurnoverMinutes' => TableLayout::MAX_TURNOVER_MINUTES,
            'stayMinutes' => $layout->stayMinutes(),
            'minStayMinutes' => TableLayout::MIN_STAY_MINUTES,
            'maxStayMinutes' => TableLayout::MAX_STAY_MINUTES,
            'floor' => $floor->board(),
            'lateMinutes' => TableFloor::LATE_MINUTES,
        ]);
    }

    public function update(UpdateTableManagementRequest $request, ReservationPricing $pricing): RedirectResponse
    {
        $pricing->saveTableFees($request->tableFees());

        return redirect()->route('tables.index')->with('status', 'Table options updated successfully.');
    }

    public function updateLayout(
        UpdateTableLayoutRequest $request,
        ReservationSchedule $schedules,
        ReservationPricing $pricing,
        TableLayout $layout,
    ): RedirectResponse {
        $tables = $request->tables();
        $turnover = (int) $request->validated('turnover_minutes');
        $stay = (int) $request->validated('stay_minutes');

        DB::transaction(function () use ($tables, $turnover, $stay, $schedules, $pricing, $layout): void {
            $schedules->lock();
            $this->ensureLayoutKeepsBookings($tables, $turnover, $schedules, $pricing);

            $keptIds = array_filter(array_column($tables, 'id'));
            DiningTable::query()->whereKeyNot($keptIds)->delete();
            // Park existing numbers out of the way so tables can swap numbers.
            DiningTable::query()->update(['number' => DB::raw('number + 100000')]);

            foreach ($tables as $table) {
                $attributes = ['number' => $table['number'], 'seats' => $table['seats'], 'active' => $table['active']];
                $table['id'] !== null
                    ? DiningTable::query()->whereKey($table['id'])->update($attributes)
                    : DiningTable::query()->create($attributes);
            }

            $layout->saveTurnoverMinutes($turnover);
            $layout->saveStayMinutes($stay);
        });

        return redirect()->route('tables.index')->with('status', 'Tables updated successfully.');
    }

    /**
     * The floor on its own, for cashiers: they can mark tables occupied or free but not change settings.
     */
    public function floor(TableFloor $floor): View
    {
        return view('tables.floor', [
            'floor' => $floor->board(),
            'lateMinutes' => TableFloor::LATE_MINUTES,
        ]);
    }

    public function seat(SeatTableRequest $request, DiningTable $diningTable, TableFloor $floor): RedirectResponse
    {
        $reservation = $request->reservation();
        $floor->seat($diningTable, $reservation, (int) $request->user()->id);

        $who = $reservation !== null ? $reservation->reference : 'Walk-in party';

        return $this->backToFloor($request->user())->with('status', "{$diningTable->label()} marked occupied ({$who}).");
    }

    public function free(Request $request, DiningTable $diningTable, TableFloor $floor): RedirectResponse
    {
        $floor->free($diningTable, (int) $request->user()->id);

        return $this->backToFloor($request->user())->with('status', "{$diningTable->label()} marked free.");
    }

    private function backToFloor(User $user): RedirectResponse
    {
        return redirect()->route($user->hasRole(User::ROLE_SUPER_ADMIN) ? 'tables.index' : 'floor.index');
    }

    /**
     * @param  list<array{id: int|null, number: int, seats: int, active: bool}>  $tables
     */
    private function ensureLayoutKeepsBookings(array $tables, int $turnover, ReservationSchedule $schedules, ReservationPricing $pricing): void
    {
        $submitted = collect($tables)->keyBy('id');
        $existing = DiningTable::query()->withCount('reservations')->get();

        foreach ($existing as $table) {
            if (! $submitted->has($table->id) && $table->reservations_count > 0) {
                throw ValidationException::withMessages([
                    'dining_tables' => "Table {$table->number} has reservations, so it can't be removed. Untick “Bookable” to stop new bookings instead.",
                ]);
            }
        }

        $requested = Reservation::query()
            ->whereNotNull('dining_table_id')
            ->whereIn('id', $schedules->active()->select('id'))
            ->where('reservation_at', '>', now()->subDay())
            ->with('diningTable')
            ->get();
        foreach ($requested as $reservation) {
            $replacement = $submitted->get($reservation->dining_table_id);
            if (($reservation->reservation_end_at ?? $reservation->reservation_at)->isPast()) {
                continue;
            }
            if ($replacement === null || ! $replacement['active'] || $replacement['seats'] < $reservation->guests) {
                throw ValidationException::withMessages([
                    'dining_tables' => "Table {$reservation->diningTable?->number} has an upcoming reservation ({$reservation->reference}) that asked for it. Keep it bookable with at least {$reservation->guests} seats, or move that reservation first.",
                ]);
            }
        }

        $activeTables = collect($tables)->where('active', true);
        $maxSeats = (int) $activeTables->max('seats');
        $largestOption = max($pricing->tableSizes());
        if ($largestOption > $maxSeats) {
            throw ValidationException::withMessages([
                'dining_tables' => "Customers can book tables for up to {$largestOption} guests, so keep a bookable table with at least {$largestOption} seats, or change the table options first.",
            ]);
        }

        $slots = $activeTables
            ->sortBy([['seats', 'asc'], ['number', 'asc']])
            ->map(fn (array $table): array => ['id' => $table['id'], 'number' => $table['number'], 'seats' => $table['seats']])
            ->values()
            ->all();
        if (! $schedules->upcomingBookingsFit($slots, $turnover)) {
            throw ValidationException::withMessages([
                'dining_tables' => 'Upcoming reservations would no longer fit with these tables and cleanup time. Keep more tables bookable, or move those reservations first.',
            ]);
        }
    }
}
