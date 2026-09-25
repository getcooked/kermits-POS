<?php

namespace App\Services;

use App\Models\SystemSetting;

class ReservationPricing
{
    public const TABLES_SETTING_KEY = 'reservation_table_types';

    private const LEGACY_TABLE_SIZES = [1, 2, 4, 8, 12];

    private const DEFAULT_TABLE_FEES = [1 => 100, 2 => 150, 4 => 250, 8 => 450, 12 => 650];

    /**
     * Reservation fee for each table option, keyed by the guest number and sorted ascending.
     *
     * @return array<int, float>
     */
    public function tableFees(): array
    {
        $saved = json_decode((string) SystemSetting::get(self::TABLES_SETTING_KEY), true);

        if (is_array($saved) && $saved !== []) {
            $fees = collect($saved)
                ->filter(fn ($table): bool => is_array($table) && is_numeric($table['guests'] ?? null) && is_numeric($table['fee'] ?? null))
                ->mapWithKeys(fn (array $table): array => [(int) $table['guests'] => (float) $table['fee']])
                ->sortKeys()
                ->all();

            if ($fees !== []) {
                return $fees;
            }
        }

        return $this->legacyTableFees();
    }

    /**
     * @return list<int>
     */
    public function tableSizes(): array
    {
        return array_keys($this->tableFees());
    }

    public function tableFee(int $size): float
    {
        return (float) ($this->tableFees()[$size] ?? 0);
    }

    public function exclusiveFee(): float
    {
        return (float) config('reservations.exclusive_fee');
    }

    /**
     * Largest party a single table in the venue can seat.
     */
    public function maxGuests(): int
    {
        return max(1, app(TableLayout::class)->maxSeats());
    }

    /**
     * @param  array<int, float|int|string>  $fees  keyed by guest number
     */
    public function saveTableFees(array $fees): void
    {
        ksort($fees);

        $tables = collect($fees)
            ->map(fn ($fee, $guests): array => [
                'guests' => (int) $guests,
                'fee' => number_format((float) $fee, 2, '.', ''),
            ])
            ->values()
            ->all();

        SystemSetting::query()->updateOrCreate(
            ['key' => self::TABLES_SETTING_KEY],
            ['value' => json_encode($tables)],
        );
    }

    /**
     * Prices saved per fixed party size before tables became editable.
     *
     * @return array<int, float>
     */
    private function legacyTableFees(): array
    {
        $configuredFees = config('reservations.table_fees') ?: self::DEFAULT_TABLE_FEES;

        return collect(self::LEGACY_TABLE_SIZES)->mapWithKeys(function (int $size) use ($configuredFees): array {
            $savedFee = SystemSetting::get('reservation_table_fee_'.$size);
            $fee = is_numeric($savedFee) ? (float) $savedFee : (float) ($configuredFees[$size] ?? 0);

            return [$size => $fee];
        })->all();
    }
}
