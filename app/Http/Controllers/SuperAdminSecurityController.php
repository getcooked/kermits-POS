<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSuperAdminPasswordRequest;
use App\Notifications\SuperAdminPasswordVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SuperAdminSecurityController extends Controller
{
    public function edit(): View
    {
        return view('staff.security');
    }

    public function sendVerificationCode(Request $request): RedirectResponse
    {
        $user = $request->user();
        $code = (string) random_int(100000, 999999);

        $request->session()->put('super_admin_password_verification', [
            'user_id' => $user->getKey(),
            'email' => strtolower($user->email),
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        $user->notify(new SuperAdminPasswordVerification($code));

        return back()->with('status', 'A 6-digit verification code was sent to '.$user->email.'.');
    }

    public function updatePassword(UpdateSuperAdminPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        $user->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
        DB::table('mobile_api_tokens')->where('user_id', $user->id)->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        $request->session()->forget('super_admin_password_verification');
        $request->session()->regenerate();

        return back()->with('status', 'Your password was changed. Other web and mobile sessions were signed out.');
    }
}
