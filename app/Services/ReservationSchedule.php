<?php

namespace App\Services;

use App\Models\DiningTable;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationSchedule
{
    public const HOURS_MESSAGE = 'Open 8:00 AM–11:00 PM. Choose an arrival from 8:00 AM to 10:00 PM. The last reservation is 10:00–11:00 PM.';

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
                $pending->where('status', 'pending')->where(function (Builder $hold) {
                    $hold->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now());
                });
            });
        });
    }

    private function conflicts(string $start, string $end, ?int $ignore = null): Builder
    {
        return $this->active()->when($ignore, fn (Builder $q) => $q->whereKeyNot($ignore))
            ->where('reservation_at', '<', $end)
            ->where(fn (Builder $q) => $q->where('reservation_end_at', '>', $start)->orWhereNull('reservation_end_at'));
    }

    private function tableFor(string $start, string $end, int $guests, ?int $ignore = null, ?int $tableId = null): ?DiningTable
    {
        $conflicts = $this->conflicts($start, $end, $ignore)->get(['type', 'dining_table_id']);
        // Unassigned legacy bookings reserve the venue until staff assigns them.
        if ($conflicts->contains(fn ($r) => $r->type === 'exclusive' || $r->dining_table_id === null)) {
            return null;
        }

        return DiningTable::query()->where('active', true)->where('capacity', '>=', $guests)
            ->when($tableId !== null, fn ($q) => $q->whereKey($tableId))
            ->whereNotIn('id', $conflicts->pluck('dining_table_id'))
            ->orderBy('capacity')->orderBy('number')->first();
    }

    public function isAvailable(string|DateTimeInterface $at, string $type = 'table', int $guests = 1): bool
    {
        if (! $this->withinHours($at)) {
            return false;
        }
        $start = $this->normalize($at);
        $end = $this->endAt($at);

        return $type === 'exclusive' ? ! $this->conflicts($start, $end)->exists()
            : $this->tableFor($start, $end, $guests) !== null;
    }

    public function lock(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Reservation lock requires a transaction.');
        }
        if (DB::getDriverName() === 'sqlite') {
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
            $table = null;
            if ($attributes['type'] === 'exclusive') {
                $available = ! $this->conflicts($start, $end)->exists();
            } else {
                $table = $this->tableFor($start, $end, (int) ($attributes['guests'] ?? $attributes['table_size']));
                $available = $table !== null;
            }
            if (! $available) {
                throw ValidationException::withMessages(['reservation_at' => 'No suitable table or venue is available for this period. Please choose another schedule.']);
            }

            return Reservation::query()->create([
                ...$attributes,
                'reservation_at' => $start,
                'reservation_end_at' => $end,
                'dining_table_id' => $table?->id,
                'status' => 'pending',
                'hold_expires_at' => now()->addMinutes(config('reservations.hold_minutes'))->min(CarbonImmutable::parse($start)),
            ]);
        }, attempts: 3);
    }

    public function reassign(Reservation $reservation, int $tableId, int $actor): void
    {
        DB::transaction(function () use ($reservation, $tableId, $actor) {
            $this->lock();
            $reservation->refresh();
            if ($reservation->type !== 'table' || ! in_array($reservation->booking_status, ['pending', 'confirmed'])
                || $reservation->reservation_end_at?->lte(now())) {
                throw ValidationException::withMessages(['dining_table_id' => 'Only active table reservations can be assigned.']);
            }
            $table = $this->tableFor($this->normalize($reservation->reservation_at),
                $reservation->reservation_end_at?->format('Y-m-d H:i:s') ?? $this->endAt($reservation->reservation_at),
                $reservation->guests, $reservation->id, $tableId);
            if (! $table) {
                throw ValidationException::withMessages(['dining_table_id' => 'That table is unavailable, overlaps another booking, or has too few seats.']);
            }
            $reservation->update(['dining_table_id' => $table->id, 'handled_by' => $actor]);
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
                $table = $reservation->type === 'table'
                    ? $this->tableFor($start, $end, $reservation->guests, $reservation->id, $reservation->dining_table_id) : null;
                if (($reservation->type === 'table' && ! $table)
                    || ($reservation->type === 'exclusive' && $this->conflicts($start, $end, $reservation->id)->exists())) {
                    throw ValidationException::withMessages(['status' => 'No suitable table or venue is available. Assign an available table before approval.']);
                }
                $reservation->dining_table_id = $table?->id;
                $reservation->reservation_end_at = $end;
            }
            $reservation->fill(['status' => $status, 'hold_expires_at' => null, 'handled_by' => $actor])->save();
            $reservation->statusHistories()->create(['from_status' => $previous, 'to_status' => $status, 'changed_by' => $actor]);
        }, attempts: 3);
    }

    public function expireHolds(): int
    {
        return DB::transaction(function () {
            $this->lock();
            $expired = Reservation::query()->where('status', 'pending')->where('hold_expires_at', '<=', now())->get();
            foreach ($expired as $reservation) {
                $reservation->update(['status' => 'expired']);
                $reservation->statusHistories()->create(['from_status' => 'pending', 'to_status' => 'expired']);
            }

            return $expired->count();
        });
    }
}
