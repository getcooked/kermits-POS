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

    public const MAX_TURNOVER_MINUTES = 120;

    private ?bool $hasDiningTables = null;

    public function hasDiningTables(): bool
    {
        return $this->hasDiningTables ??= Schema::hasTable('dining_tables');
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
     * Seating capacity the scheduler allocates from, smallest first.
     *
     * @return list<array{id: int|null, number: int|null, seats: int}>
     */
    public function slots(): array
    {
        if ($this->hasDiningTables()) {
            return $this->activeTables()
                ->map(fn (DiningTable $table): array => ['id' => $table->id, 'number' => $table->number, 'seats' => $table->seats])
                ->all();
        }

        // Code-only deployments reach this before the dining_tables migration runs.
        $capacities = array_map('intval', (array) config('reservations.table_capacities', []));
        sort($capacities);

        return array_map(fn (int $seats): array => ['id' => null, 'number' => null, 'seats' => $seats], $capacities);
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
}
