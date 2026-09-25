<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReservationSchedule
{
    public const HOURS_MESSAGE = 'Open 8:00 AM–11:00 PM. Choose an arrival from 8:00 AM to 10:00 PM. The last reservation is 10:00–11:00 PM.';

    private ?bool $hasReservationEndAt = null;

    private ?bool $hasHoldExpiresAt = null;

    private ?bool $hasReservationLock = null;

    private ?bool $hasLegacyUniqueSchedule = null;

    private ?bool $hasDiningTableColumn = null;

    public function __construct(private readonly TableLayout $layout)
    {
        $this->hasDiningTableColumn = Schema::hasColumn('reservations', 'dining_table_id');
        $this->hasReservationEndAt = Schema::hasColumn('reservations', 'reservation_end_at');
        $this->hasHoldExpiresAt = Schema::hasColumn('reservations', 'hold_expires_at');
        $this->hasReservationLock = Schema::hasTable('reservation_locks');
        $this->hasLegacyUniqueSchedule = Schema::hasIndex('reservations', 'reservations_reservation_at_unique');
    }

    private function hasReservationEndAt(): bool
    {
        return $this->hasReservationEndAt ??= Schema::hasColumn('reservations', 'reservation_end_at');
    }

    private function hasHoldExpiresAt(): bool
    {
        return $this->hasHoldExpiresAt ??= Schema::hasColumn('reservations', 'hold_expires_at');
    }

    private function hasReservationLock(): bool
    {
        return $this->hasReservationLock ??= Schema::hasTable('reservation_locks');
    }

    private function hasLegacyUniqueSchedule(): bool
    {
        return $this->hasLegacyUniqueSchedule ??= Schema::hasIndex('reservations', 'reservations_reservation_at_unique');
    }

    private function hasDiningTableColumn(): bool
    {
        return $this->hasDiningTableColumn ??= Schema::hasColumn('reservations', 'dining_table_id');
    }

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

    public function withinHours(string|DateTimeInterface $at): bool
    {
        $time = CarbonImmutable::parse($this->normalize($at))->format('H:i:s');

        return $time >= config('reservations.opening_time') && $time <= config('reservations.last_start_time');
    }

    public function endAt(string|DateTimeInterface $at): string
    {
        $start = CarbonImmutable::parse($this->normalize($at));
        $closing = $start->setTimeFromTimeString(config('reservations.closing_time'));

        return $start->addMinutes(config('reservations.duration_minutes'))->min($closing)->format('Y-m-d H:i:s');
    }

    public function active(): Builder
    {
        return Reservation::query()->where(function (Builder $query) {
            $query->where('status', 'confirmed')->orWhere(function (Builder $pending) {
                $pending->where('status', 'pending');
                if ($this->hasHoldExpiresAt()) {
                    $pending->where(function (Builder $hold) {
                        $hold->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now());
                    });
                } else {
                    $pending->where(function (Builder $hold) {
                        $hold->where('payment_status', 'paid')
                            ->orWhere('created_at', '>', now()->subMinutes(config('reservations.hold_minutes')));
                    });
                }
            });
        });
    }

    /**
     * Active bookings whose period, plus the cleanup time either side, overlaps [start, end).
     */
    private function conflicts(string $start, string $end, ?int $ignore = null, int $turnoverMinutes = 0): Builder
    {
        $windowStart = CarbonImmutable::parse($start)->subMinutes($turnoverMinutes)->format('Y-m-d H:i:s');
        $windowEnd = CarbonImmutable::parse($end)->addMinutes($turnoverMinutes)->format('Y-m-d H:i:s');
        $query = $this->active()->when($ignore, fn (Builder $q) => $q->whereKeyNot($ignore))
            ->where('reservation_at', '<', $windowEnd);

        return $this->hasReservationEndAt()
            ? $query->where(fn (Builder $q) => $q->where('reservation_end_at', '>', $windowStart)->orWhereNull('reservation_end_at'))
            : $query->where('reservation_at', '>', CarbonImmutable::parse($windowStart)
                ->subMinutes(config('reservations.duration_minutes'))->format('Y-m-d H:i:s'));
    }

    private function hasCapacity(string $start, string $end, int $guests, ?int $ignore = null, ?int $tableId = null): bool
    {
        $turnover = $this->layout->turnoverMinutes();
        $bookings = $this->conflicts($start, $end, $ignore, $turnover)->get($this->conflictColumns());

        return $this->hasCapacityForBookings($bookings, $start, $end, $guests, $this->layout->slots(), $turnover, $tableId);
    }

    private function conflictColumns(): array
    {
        $columns = ['id', 'type', 'guests', 'table_size', 'reservation_at'];
        if ($this->hasReservationEndAt()) {
            $columns[] = 'reservation_end_at';
        }
        if ($this->hasDiningTableColumn()) {
            $columns[] = 'dining_table_id';
        }

        return $columns;
    }

    /**
     * @return array{start: string, end: string, guests: int, table: int|null}
     */
    private function period(Reservation $reservation): array
    {
        return [
            'start' => $this->normalize($reservation->reservation_at),
            'end' => $reservation->reservation_end_at?->format('Y-m-d H:i:s') ?? $this->endAt($reservation->reservation_at),
            'guests' => (int) ($reservation->guests ?: $reservation->table_size),
            'table' => $this->hasDiningTableColumn() && $reservation->dining_table_id ? (int) $reservation->dining_table_id : null,
        ];
    }

    private function hasCapacityForBookings(
        Collection $bookings,
        string $start,
        string $end,
        int $guests,
        array $slots,
        int $turnoverMinutes,
        ?int $tableId = null,
    ): bool {
        if ($bookings->contains(fn (Reservation $reservation): bool => $reservation->type === 'exclusive')) {
            return false;
        }

        $periods = $bookings->map(fn (Reservation $reservation): array => $this->period($reservation))
            ->push(['start' => $start, 'end' => $end, 'guests' => $guests, 'table' => $tableId])
            ->all();

        return $this->allocate($periods, $slots, $turnoverMinutes);
    }

    /**
     * How many more parties of this size could still be seated for [start, end).
     */
    private function tablesLeft(Collection $bookings, string $start, string $end, int $guests, array $slots, int $turnoverMinutes): int
    {
        if ($bookings->contains(fn (Reservation $reservation): bool => $reservation->type === 'exclusive')) {
            return 0;
        }

        $periods = $bookings->map(fn (Reservation $reservation): array => $this->period($reservation))->all();
        $left = 0;
        while ($left < count($slots)) {
            $periods[] = ['start' => $start, 'end' => $end, 'guests' => $guests, 'table' => null];
            if (! $this->allocate($periods, $slots, $turnoverMinutes)) {
                break;
            }
            $left++;
        }

        return $left;
    }

    /**
     * Seat every period: requested tables first, then the remaining parties in
     * start order at the smallest free table that fits. A table must stay
     * empty for the cleanup time after each booking.
     */
    private function allocate(array $periods, array $slots, int $turnoverMinutes): bool
    {
        $turnoverSeconds = $turnoverMinutes * 60;
        $tables = array_map(fn (array $slot): array => [...$slot, 'busy' => []], $slots);
        $indexById = [];
        foreach ($tables as $index => $table) {
            if ($table['id'] !== null) {
                $indexById[$table['id']] = $index;
            }
        }

        $unassigned = [];
        foreach ($periods as $period) {
            $index = $period['table'] !== null ? ($indexById[$period['table']] ?? null) : null;
            if ($index === null || $tables[$index]['seats'] < $period['guests']) {
                $unassigned[] = $period;

                continue;
            }
            if ($this->isBusy($tables[$index]['busy'], $period, $turnoverSeconds)) {
                return false;
            }
            $tables[$index]['busy'][] = $period;
        }

        usort($unassigned, fn (array $left, array $right): int => [$left['start'], -$left['guests']] <=> [$right['start'], -$right['guests']]);

        foreach ($unassigned as $period) {
            $seated = false;
            foreach ($tables as $index => $table) {
                if ($table['seats'] >= $period['guests'] && ! $this->isBusy($table['busy'], $period, $turnoverSeconds)) {
                    $tables[$index]['busy'][] = $period;
                    $seated = true;
                    break;
                }
            }
            if (! $seated) {
                return false;
            }
        }

        return true;
    }

    private function isBusy(array $busy, array $period, int $turnoverSeconds): bool
    {
        $start = strtotime($period['start']);
        $end = strtotime($period['end']);

        foreach ($busy as $other) {
            if ($start < strtotime($other['end']) + $turnoverSeconds && strtotime($other['start']) < $end + $turnoverSeconds) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether every upcoming table booking still fits when seated at these tables.
     *
     * @param  list<array{id: int|null, number: int|null, seats: int}>  $slots
     */
    public function upcomingBookingsFit(array $slots, int $turnoverMinutes): bool
    {
        $query = $this->active()->where('type', 'table');
        $query = $this->hasReservationEndAt()
            ? $query->where(fn (Builder $q) => $q->where('reservation_end_at', '>', now())->orWhereNull('reservation_end_at'))
            : $query->where('reservation_at', '>', now()->subMinutes(config('reservations.duration_minutes')));

        $periods = $query->get($this->conflictColumns())
            ->map(fn (Reservation $reservation): array => $this->period($reservation))
            ->all();

        return $this->allocate($periods, $slots, $turnoverMinutes);
    }

    public function availabilityForDate(string|DateTimeInterface $date, string $type = 'table', int $guests = 1, ?int $tableId = null): array
    {
        $date = CarbonImmutable::parse($date, config('app.timezone'))->setTimezone(config('app.timezone'));
        $start = $date->setTimeFromTimeString(config('reservations.opening_time'));
        $last = $date->setTimeFromTimeString(config('reservations.last_start_time'));
        $dayEnd = $date->setTimeFromTimeString(config('reservations.closing_time'));
        $turnover = $this->layout->turnoverMinutes();
        $tables = $this->layout->slots();
        $bookings = $this->conflicts($start->format('Y-m-d H:i:s'), $dayEnd->format('Y-m-d H:i:s'), turnoverMinutes: $turnover)
            ->get($this->conflictColumns());
        $now = now();
        $slots = [];

        for ($at = $start; $at->lte($last); $at = $at->addMinutes(30)) {
            $slotStart = $at->format('Y-m-d H:i:s');
            $slotEnd = $this->endAt($at);
            $windowStart = $at->subMinutes($turnover)->format('Y-m-d H:i:s');
            $windowEnd = CarbonImmutable::parse($slotEnd)->addMinutes($turnover)->format('Y-m-d H:i:s');
            $slotBookings = $bookings->filter(function (Reservation $reservation) use ($windowStart, $windowEnd): bool {
                $reservationStart = $this->normalize($reservation->reservation_at);
                $reservationEnd = $reservation->reservation_end_at?->format('Y-m-d H:i:s')
                    ?? CarbonImmutable::parse($reservationStart)->addMinutes(config('reservations.duration_minutes'))->format('Y-m-d H:i:s');

                return $reservationStart < $windowEnd && $reservationEnd > $windowStart;
            });
            $available = $at->gt($now);
            if ($available && $type === 'table' && $this->hasLegacyUniqueSchedule()
                && $slotBookings->contains(fn (Reservation $reservation): bool => $this->normalize($reservation->reservation_at) === $slotStart)) {
                $available = false;
            } elseif ($available) {
                $available = $type === 'exclusive'
                    ? $slotBookings->isEmpty()
                    : $this->hasCapacityForBookings($slotBookings, $slotStart, $slotEnd, $guests, $tables, $turnover, $tableId);
            }

            $tablesLeft = null;
            if ($type === 'table') {
                $tablesLeft = match (true) {
                    ! $available => 0,
                    $tableId !== null => 1,
                    default => $this->tablesLeft($slotBookings, $slotStart, $slotEnd, $guests, $tables, $turnover),
                };
            }

            $end = CarbonImmutable::parse($slotEnd);
            $slots[] = [
                'start' => $at->format('Y-m-d\\TH:i'),
                'end' => $end->format('Y-m-d\\TH:i'),
                'label' => $at->format('g:i A').' – '.$end->format('g:i A'),
                'available' => $available,
                'tables_left' => $tablesLeft,
            ];
        }

        return $slots;
    }

    public function isAvailable(string|DateTimeInterface $at, string $type = 'table', int $guests = 1, ?int $tableId = null): bool
    {
        if (! $this->withinHours($at)) {
            return false;
        }
        $start = $this->normalize($at);
        $end = $this->endAt($at);

        if ($type === 'table' && $this->hasLegacyUniqueSchedule()
            && $this->active()->where('reservation_at', $start)->exists()) {
            return false;
        }

        return $type === 'exclusive'
            ? ! $this->conflicts($start, $end, turnoverMinutes: $this->layout->turnoverMinutes())->exists()
            : $this->hasCapacity($start, $end, $guests, tableId: $tableId);
    }

    public function customerHasOverlap(int $userId, string|DateTimeInterface $at, ?int $ignore = null): bool
    {
        $start = $this->normalize($at);

        return $this->conflicts($start, $this->endAt($start), $ignore)->where('user_id', $userId)->exists();
    }

    public function lock(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Reservation lock requires a transaction.');
        }
        if (! $this->hasReservationLock()) {
            if (DB::getDriverName() === 'sqlite') {
                DB::table('migrations')->orderBy('id')->limit(1)->update(['batch' => DB::raw('batch')]);
            } else {
                DB::table('migrations')->orderBy('id')->lockForUpdate()->firstOrFail();
            }
        } elseif (DB::getDriverName() === 'sqlite') {
            // SQLite ignores FOR UPDATE. A write acquires its database write lock
            // before any availability read, including with an empty schedule.
            DB::table('reservation_locks')->where('id', 1)->update(['id' => 1]);
        } else {
            DB::table('reservation_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
        }
    }

    public function reserve(array $attributes): Reservation
    {
        return DB::transaction(function () use ($attributes) {
            $this->lock();
            $at = $attributes['reservation_at'];
            if (! $this->withinHours($at) || CarbonImmutable::parse($this->normalize($at))->lte(now())) {
                throw ValidationException::withMessages(['reservation_at' => self::HOURS_MESSAGE.' Please choose a future time.']);
            }
            $start = $this->normalize($at);
            $end = $this->endAt($at);
            $guests = (int) ($attributes['guests'] ?? $attributes['table_size']);
            $tableId = $attributes['type'] === 'table' && filled($attributes['dining_table_id'] ?? null)
                ? (int) $attributes['dining_table_id']
                : null;
            unset($attributes['dining_table_id']);
            if ($this->hasDiningTableColumn()) {
                $attributes['dining_table_id'] = $tableId;
            }

            if (filled($attributes['user_id'] ?? null) && $this->customerHasOverlap((int) $attributes['user_id'], $start)) {
                throw ValidationException::withMessages(['reservation_at' => 'You already have a reservation during this time. Choose another time or cancel your other reservation first.']);
            }
            if ($attributes['type'] === 'table' && $this->hasLegacyUniqueSchedule()
                && $this->active()->where('reservation_at', $start)->exists()) {
                throw ValidationException::withMessages(['reservation_at' => 'This exact start time is already reserved. Please choose another available time.']);
            }

            $requestedTable = null;
            if ($tableId !== null) {
                $requestedTable = collect($this->layout->slots())->firstWhere('id', $tableId);
                if ($requestedTable === null || $requestedTable['seats'] < $guests) {
                    throw ValidationException::withMessages(['dining_table_id' => 'That table cannot seat your party. Choose another table or any available table.']);
                }
            }

            if ($attributes['type'] === 'exclusive') {
                $available = ! $this->conflicts($start, $end, turnoverMinutes: $this->layout->turnoverMinutes())->exists();
            } else {
                $available = $this->hasCapacity($start, $end, $guests, tableId: $tableId);
            }
            if (! $available) {
                throw ValidationException::withMessages($requestedTable !== null
                    ? ['dining_table_id' => "Table {$requestedTable['number']} is already booked for this time. Choose another time, another table, or any available table."]
                    : ['reservation_at' => 'No suitable table or venue is available for this period. Please choose another schedule.']);
            }

            $periodAttributes = [];
            if ($this->hasReservationEndAt()) {
                $periodAttributes['reservation_end_at'] = $end;
            }
            if ($this->hasHoldExpiresAt()) {
                $periodAttributes['hold_expires_at'] = now()->addMinutes(config('reservations.hold_minutes'))->min(CarbonImmutable::parse($start));
            }

            return Reservation::query()->create([
                ...$attributes,
                'reservation_at' => $start,
                ...$periodAttributes,
                'status' => 'pending',
            ]);
        }, attempts: 3);
    }

    public function changeStatus(Reservation $reservation, string $status, int $actor): void
    {
        DB::transaction(function () use ($reservation, $status, $actor) {
            $this->lock();
            $reservation->refresh();
            $previous = $reservation->booking_status;
            $allowed = match ($previous) {
                'pending' => ['confirmed', 'rejected', 'cancelled'],
                'confirmed' => ['completed', 'cancelled'],
                default => [],
            };
            if (! in_array($status, $allowed)) {
                throw ValidationException::withMessages(['status' => 'This reservation has expired or its status has already changed. Refresh the page.']);
            }
            if ($status === 'confirmed') {
                if ($reservation->reservation_at->lte(now())) {
                    throw ValidationException::withMessages(['status' => 'A past reservation cannot be confirmed.']);
                }
                $start = $this->normalize($reservation->reservation_at);
                $end = $reservation->reservation_end_at?->format('Y-m-d H:i:s') ?? $this->endAt($start);
                $tableId = $this->hasDiningTableColumn() && $reservation->dining_table_id ? (int) $reservation->dining_table_id : null;
                if (($reservation->type === 'table' && ! $this->hasCapacity($start, $end, $reservation->guests, $reservation->id, $tableId))
                    || ($reservation->type === 'exclusive' && $this->conflicts($start, $end, $reservation->id, $this->layout->turnoverMinutes())->exists())) {
                    throw ValidationException::withMessages(['status' => 'No suitable capacity or venue is available for this reservation.']);
                }
                if ($this->hasReservationEndAt()) {
                    $reservation->reservation_end_at = $end;
                }
            }
            $updates = ['status' => $status, 'handled_by' => $actor];
            if ($this->hasHoldExpiresAt()) {
                $updates['hold_expires_at'] = null;
            }
            $reservation->fill($updates)->save();
            $reservation->statusHistories()->create(['from_status' => $previous, 'to_status' => $status, 'changed_by' => $actor]);
        }, attempts: 3);
    }

    public function expireHolds(): int
    {
        return DB::transaction(function () {
            $this->lock();
            $expired = Reservation::query()->where('status', 'pending')
                ->when(
                    $this->hasHoldExpiresAt(),
                    fn (Builder $query) => $query->where('hold_expires_at', '<=', now()),
                    fn (Builder $query) => $query->where('payment_status', '!=', 'paid')
                        ->where('created_at', '<=', now()->subMinutes(config('reservations.hold_minutes'))),
                )->get();
            foreach ($expired as $reservation) {
                $reservation->update(['status' => 'expired']);
                $reservation->statusHistories()->create(['from_status' => 'pending', 'to_status' => 'expired']);
            }

            return $expired->count();
        });
    }
}
