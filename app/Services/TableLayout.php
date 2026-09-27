<?php

namespace App\Services;

use App\Models\DiningTable;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class TableLayout
{
    public const TURNOVER_SETTING_KEY = 'reservation_turnover_minutes';

    public const STAY_SETTING_KEY = 'reservation_stay_minutes';

    public const MAX_TURNOVER_MINUTES = 120;

    public const MIN_STAY_MINUTES = 30;

    public const MAX_STAY_MINUTES = 480;

    private ?bool $hasDiningTables = null;

    private ?bool $hasFloorStatus = null;

    public function hasDiningTables(): bool
    {
        return $this->hasDiningTables ??= Schema::hasTable('dining_tables');
    }

    /**
     * Whether staff can mark tables occupied and free (the floor-status migration has run).
     */
    public function hasFloorStatus(): bool
    {
        return $this->hasFloorStatus ??= $this->hasDiningTables() && Schema::hasColumn('dining_tables', 'occupied_at');
    }

    /**
     * Active numbered tables, smallest first.
     *
     * @return Collection<int, DiningTable>
     */
    public function activeTables(): Collection
    {
        if (! $this->hasDiningTables()) {
            return collect();
        }

        return DiningTable::query()->active()->orderBy('seats')->orderBy('number')->get();
    }

    /**
     * Seating capacity the scheduler allocates from, smallest first. `busy`
     * holds what staff marked on the floor: an occupied table is taken until
     * its expected free time (or now, if the party stays longer), and a table
     * freed moments ago still needs its cleanup time.
     *
     * @return list<array{id: int|null, number: int|null, seats: int, busy: list<array{start: string, end: string, guests: int, table: int|null}>}>
     */
    public function slots(): array
    {
        if ($this->hasDiningTables()) {
            return $this->activeTables()
                ->map(fn (DiningTable $table): array => [
                    'id' => $table->id,
                    'number' => $table->number,
                    'seats' => $table->seats,
                    'busy' => $this->floorPeriods($table),
                ])
                ->all();
        }

        // Code-only deployments reach this before the dining_tables migration runs.
        $capacities = array_map('intval', (array) config('reservations.table_capacities', []));
        sort($capacities);

        return array_map(fn (int $seats): array => ['id' => null, 'number' => null, 'seats' => $seats, 'busy' => []], $capacities);
    }

    /**
     * @return list<array{start: string, end: string, guests: int, table: int|null}>
     */
    private function floorPeriods(DiningTable $table): array
    {
        if (! $this->hasFloorStatus()) {
            return [];
        }

        $now = now();
        $periods = [];
        if ($table->occupied_at !== null) {
            $until = $table->expected_free_at?->max($now) ?? $now;
            $periods[] = $this->floorPeriod($table, $table->occupied_at->format('Y-m-d H:i:s'), $until->format('Y-m-d H:i:s'));
        }
        if ($table->freed_at !== null && $table->freed_at->gt($now->copy()->subMinutes($this->turnoverMinutes()))) {
            $freed = $table->freed_at->format('Y-m-d H:i:s');
            $periods[] = $this->floorPeriod($table, $freed, $freed);
        }

        return $periods;
    }

    private function floorPeriod(DiningTable $table, string $start, string $end): array
    {
        return ['start' => $start, 'end' => $end, 'guests' => $table->seats, 'table' => $table->id];
    }

    /**
     * Validation for the optional "request a specific table" field.
     */
    public function requestRules(): array
    {
        if (! $this->hasDiningTables()) {
            return ['nullable', 'prohibited'];
        }

        return ['nullable', 'integer', Rule::exists('dining_tables', 'id')->where('active', true)];
    }

    public function maxSeats(): int
    {
        return (int) max([0, ...array_column($this->slots(), 'seats')]);
    }

    public function turnoverMinutes(): int
    {
        $saved = SystemSetting::get(self::TURNOVER_SETTING_KEY);
        $minutes = is_numeric($saved) ? (int) $saved : (int) config('reservations.turnover_minutes', 0);

        return max(0, min(self::MAX_TURNOVER_MINUTES, $minutes));
    }

    public function saveTurnoverMinutes(int $minutes): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => self::TURNOVER_SETTING_KEY],
            ['value' => (string) $minutes],
        );
    }

    /**
     * How long a party is expected to stay. Customers never see it; it only
     * spaces out bookings on the same table until staff free it by hand.
     */
    public function stayMinutes(): int
    {
        $saved = SystemSetting::get(self::STAY_SETTING_KEY);
        $minutes = is_numeric($saved) ? (int) $saved : (int) config('reservations.duration_minutes', 120);

        return max(self::MIN_STAY_MINUTES, min(self::MAX_STAY_MINUTES, $minutes));
    }

    public function saveStayMinutes(int $minutes): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => self::STAY_SETTING_KEY],
            ['value' => (string) $minutes],
        );
    }
}
