<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePaymentSettingsRequest;
use App\Http\Requests\UpdateReservationPricingRequest;
use App\Models\SystemSetting;
use App\Services\ReservationPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class PaymentSettingsController extends Controller
{
    public function edit(ReservationPricing $pricing): View
    {
        return view('settings.payment', [
            'qrPath' => SystemSetting::get('gcash_qr_path'),
            'tableFees' => $pricing->tableFees(),
        ]);
    }

    public function update(UpdatePaymentSettingsRequest $request): RedirectResponse
    {
        $oldPath = SystemSetting::get('gcash_qr_path');
        $newPath = $request->file('gcash_qr')->store('payment', 'public');

        try {
            SystemSetting::query()->updateOrCreate(['key' => 'gcash_qr_path'], ['value' => $newPath]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPath);

            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('status', 'GCash QR image updated successfully.');
    }

    public function updateReservationPricing(
        UpdateReservationPricingRequest $request,
        ReservationPricing $pricing,
    ): RedirectResponse {
        $fees = $request->validated('table_fees');

        DB::transaction(function () use ($fees, $pricing): void {
            foreach (ReservationPricing::TABLE_SIZES as $size) {
                SystemSetting::query()->updateOrCreate(
                    ['key' => $pricing->settingKey($size)],
                    ['value' => number_format((float) $fees[$size], 2, '.', '')],
                );
            }
        });

        return back()->with('status', 'Party size prices updated successfully.');
    }
}
