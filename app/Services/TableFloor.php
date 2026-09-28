<?php

namespace App\Services;

use App\Models\DiningTable;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff mark tables occupied and free by hand. Customers book an arrival time
 * only, so this, not the booking's estimated stay, says when a table is free.
 */
class TableFloor
{
    /** How far ahead a booking for an occupied table is flagged. */
    public const WARN_MINUTES = 60;

    /** How late an arrival can be before it is flagged. */
    public const LATE_MINUTES = 15;

    public function __construct(
        private readonly ReservationSchedule $schedules,
        private readonly TableLayout $layout,
    ) {}

    /**
     * Every table with what staff need to act on it now.
     *
     * @return array{tables: list<array<string, mixed>>, arrivals: Collection<int, Reservation>, crowded: bool}
     */
    public function board(): array
    {
        if (! $this->layout->hasFloorStatus()) {
            return ['tables' => [], 'arrivals' => collect(), 'crowded' => false];
        }

        $now = CarbonImmutable::now();
        $turnover = $this->layout->turnoverMinutes();
        $upcoming = $this->schedules->active()
            ->where('type', 'table')
            ->whereNull('seated_at')
            ->whereNotNull('dining_table_id')
            ->whereBetween('reservation_at', [$now->subMinutes(self::LATE_MINUTES)->format('Y-m-d H:i:s'), $now->endOfDay()->format('Y-m-d H:i:s')])
            ->orderBy('reservation_at')
            ->get(['id', 'reference', 'customer_name', 'guests', 'reservation_at', 'dining_table_id'])
            ->groupBy('dining_table_id');

        $tables = DiningTable::query()->with('occupiedReservation')->orderBy('number')->get()
            ->map(function (DiningTable $table) use ($now, $turnover, $upcoming): array {
                $next = $upcoming->get($table->id)?->first();
                $cleaningUntil = $table->freed_at?->copy()->addMinutes($turnover);

                return [
                    'table' => $table,
                    'reservation' => $table->occupiedReservation,
                    'overstaying' => $table->isOccupied() && $table->expected_free_at?->lt($now),
                    'cleaning_until' => ! $table->isOccupied() && $cleaningUntil?->gt($now) ? $cleaningUntil : null,
                    'next' => $next,
                    'next_soon' => $table->isOccupied() && $next !== null && $next->reservation_at->lte($now->addMinutes(self::WARN_MINUTES)),
                ];
            })
            ->all();

        return [
            'tables' => $tables,
            'arrivals' => $this->arrivals(),
            'exclusive' => $this->exclusiveToday(),
            // Occupied tables can leave booked parties with nowhere to sit.
            'crowded' => ! $this->schedules->upcomingBookingsFit($this->layout->slots(), $turnover),
        ];
    }

    /**
     * Approved table bookings for today that have not been seated yet.
     *
     * @return Collection<int, Reservation>
     */
    public function arrivals(): Collection
    {
        $today = CarbonImmutable::today();

        return Reservation::query()
            ->with('diningTable')
            ->where('status', 'confirmed')
            ->where('type', 'table')
            ->whereNull('seated_at')
            ->whereBetween('reservation_at', [$today->format('Y-m-d H:i:s'), $today->endOfDay()->format('Y-m-d H:i:s')])
            ->orderBy('reservation_at')
            ->get();
    }

    /**
     * Today's Exclusive Venue booking: the restaurant is reserved for it, so walk-ins are turned away.
     */
    public function exclusiveToday(): ?Reservation
    {
        $today = CarbonImmutable::today();

        return $this->schedules->active()
            ->where('type', 'exclusive')
            ->whereBetween('reservation_at', [$today->format('Y-m-d H:i:s'), $today->endOfDay()->format('Y-m-d H:i:s')])
            ->first();
    }

    public function isLate(Reservation $reservation): bool
    {
        return $reservation->reservation_at->lt(now()->subMinutes(self::LATE_MINUTES));
    }

    /**
     * Mark a table occupied, by a walk-in party or by today's booking.
     */
    public function seat(DiningTable $table, ?Reservation $reservation, int $actor): void
    {
        DB::transaction(function () use ($table, $reservation, $actor): void {
            $this->schedules->lock();
            $table->refresh();
            if ($table->isOccupied()) {
                throw ValidationException::withMessages(['floor' => "{$table->label()} is already occupied. Mark it free first."]);
            }
            if (! $table->active) {
                throw ValidationException::withMessages(['floor' => "{$table->label()} is unavailable. Make it available first."]);
            }

            if ($reservation === null && ($event = $this->exclusiveToday()) !== null) {
                throw ValidationException::withMessages(['floor' => "Kermit's is reserved today for an Exclusive Venue event ({$event->reference}), so walk-in parties can't be seated."]);
            }

            if ($reservation !== null) {
                $reservation->refresh();
                if ($reservation->type !== 'table' || $reservation->booking_status !== 'confirmed'
                    || $reservation->seated_at !== null || ! $reservation->reservation_at->isToday()) {
                    throw ValidationException::withMessages(['floor' => 'Only approved table bookings for today that are not seated yet can be seated. Refresh the page.']);
                }
                if ($table->seats < $reservation->guests) {
                    throw ValidationException::withMessages(['floor' => "{$table->label()} seats {$table->seats}, but {$reservation->reference} is for {$reservation->guests} guests. Choose a bigger table."]);
                }
                $reservation->forceFill(['seated_at' => now(), 'dining_table_id' => $table->id, 'handled_by' => $actor])->save();
            }

            $table->forceFill([
                'occupied_at' => now(),
                'expected_free_at' => now()->addMinutes($this->layout->stayMinutes()),
                'occupied_reservation_id' => $reservation?->id,
                'freed_at' => null,
            ])->save();
        });
    }

    /**
     * The party has left: free the table and complete its booking at the real leaving time.
     */
    public function free(DiningTable $table, int $actor): void
    {
        DB::transaction(function () use ($table, $actor): void {
            $this->schedules->lock();
            $table->refresh();
            if (! $table->isOccupied()) {
                throw ValidationException::withMessages(['floor' => "{$table->label()} is already free."]);
            }

            $reservation = $table->occupiedReservation;
            if ($reservation !== null && $reservation->status === 'confirmed') {
                $reservation->forceFill(['status' => 'completed', 'reservation_end_at' => now(), 'handled_by' => $actor])->save();
                $reservation->statusHistories()->create(['from_status' => 'confirmed', 'to_status' => 'completed', 'changed_by' => $actor]);
            }

            $table->forceFill([
                'occupied_at' => null,
                'expected_free_at' => null,
                'occupied_reservation_id' => null,
                'freed_at' => now(),
            ])->save();
        });
    }
}
