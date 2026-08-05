<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePaymentSettingsRequest;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PaymentSettingsController extends Controller
{
    public function edit(): View
    {
        return view('settings.payment', ['qrPath' => SystemSetting::get('gcash_qr_path')]);
    }

    public function update(UpdatePaymentSettingsRequest $request): RedirectResponse
    {
        $oldPath = SystemSetting::get('gcash_qr_path');
        $newPath = $request->file('gcash_qr')->store('payment', 'public');

        SystemSetting::query()->updateOrCreate(['key' => 'gcash_qr_path'], ['value' => $newPath]);

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('status', 'GCash QR image updated successfully.');
    }
}
