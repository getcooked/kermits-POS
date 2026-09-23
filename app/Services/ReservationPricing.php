<?php

namespace App\Services;

use App\Models\SystemSetting;

class ReservationPricing
{
    public const TABLE_SIZES = [1, 2, 4, 8, 12];

    public function tableFees(): array
    {
        $configuredFees = config('reservations.table_fees') ?: [1 => 100, 2 => 150, 4 => 250, 8 => 450, 12 => 650];

        return collect(self::TABLE_SIZES)->mapWithKeys(function (int $size) use ($configuredFees): array {
            $savedFee = SystemSetting::get($this->settingKey($size));
            $fee = is_numeric($savedFee) ? (float) $savedFee : (float) ($configuredFees[$size] ?? 0);

            return [$size => $fee];
        })->all();
    }

    public function tableFee(int $size): float
    {
        return (float) ($this->tableFees()[$size] ?? 0);
    }

    public function exclusiveFee(): float
    {
        return (float) config('reservations.exclusive_fee');
    }

    public function settingKey(int $size): string
    {
        return 'reservation_table_fee_'.$size;
    }
}
