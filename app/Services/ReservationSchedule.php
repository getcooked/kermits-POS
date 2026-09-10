<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
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

    public function __construct()
    {
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

    private function conflicts(string $start, string $end, ?int $ignore = null): Builder
    {
        $query = $this->active()->when($ignore, fn (Builder $q) => $q->whereKeyNot($ignore))
            ->where('reservation_at', '<', $end);

        return $this->hasReservationEndAt()
            ? $query->where(fn (Builder $q) => $q->where('reservation_end_at', '>', $start)->orWhereNull('reservation_end_at'))
            : $query->where('reservation_at', '>', CarbonImmutable::parse($start)
                ->subMinutes(config('reservations.duration_minutes'))->format('Y-m-d H:i:s'));
    }

    private function hasCapacity(string $start, string $end, int $guests, ?int $ignore = null): bool
    {
        $columns = ['id', 'type', 'guests', 'table_size', 'reservation_at'];
        if ($this->hasReservationEndAt()) {
            $columns[] = 'reservation_end_at';
        }
        $bookings = $this->conflicts($start, $end, $ignore)->get($columns);
        if ($bookings->contains(fn (Reservation $reservation): bool => $reservation->type === 'exclusive')) {
            return false;
        }

        $periods = $bookings->map(fn (Reservation $reservation): array => [
            'start' => $this->normalize($reservation->reservation_at),
            'end' => $reservation->reservation_end_at?->format('Y-m-d H:i:s') ?? $this->endAt($reservation->reservation_at),
            'guests' => (int) ($reservation->guests ?: $reservation->table_size),
        ])->push(['start' => $start, 'end' => $end, 'guests' => $guests])->all();

        usort($periods, fn (array $left, array $right): int => [$left['start'], -$left['guests']] <=> [$right['start'], -$right['guests']]);
        $capacities = array_map('intval', (array) config('reservations.table_capacities', []));
        sort($capacities);
        $slots = array_map(
            fn (int $capacity): array => ['capacity' => $capacity, 'available_at' => null],
            $capacities,
        );

        foreach ($periods as $period) {
            $slotIndex = null;
            foreach ($slots as $index => $slot) {
                if ($slot['capacity'] >= $period['guests']
                    && ($slot['available_at'] === null || $slot['available_at'] <= $period['start'])) {
                    $slotIndex = $index;
                    break;
                }
            }
            if ($slotIndex === null) {
                return false;
            }
            $slots[$slotIndex]['available_at'] = $period['end'];
        }

        return true;
    }

    public function isAvailable(string|DateTimeInterface $at, string $type = 'table', int $guests = 1): bool
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

        return $type === 'exclusive' ? ! $this->conflicts($start, $end)->exists()
            : $this->hasCapacity($start, $end, $guests);
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
            if ($attributes['type'] === 'table' && $this->hasLegacyUniqueSchedule()
                && $this->active()->where('reservation_at', $start)->exists()) {
                throw ValidationException::withMessages(['reservation_at' => 'This exact start time is already reserved. Please choose another available time.']);
            }
            if ($attributes['type'] === 'exclusive') {
                $available = ! $this->conflicts($start, $end)->exists();
            } else {
                $available = $this->hasCapacity($start, $end, (int) ($attributes['guests'] ?? $attributes['table_size']));
            }
            if (! $available) {
                throw ValidationException::withMessages(['reservation_at' => 'No suitable table or venue is available for this period. Please choose another schedule.']);
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
                if (($reservation->type === 'table' && ! $this->hasCapacity($start, $end, $reservation->guests, $reservation->id))
                    || ($reservation->type === 'exclusive' && $this->conflicts($start, $end, $reservation->id)->exists())) {
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
