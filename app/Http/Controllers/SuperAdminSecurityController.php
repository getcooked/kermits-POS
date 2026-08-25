<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSuperAdminPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SuperAdminSecurityController extends Controller
{
    public function edit(): View
    {
        return view('staff.security');
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

        $request->session()->regenerate();

        return back()->with('status', 'Your password was changed. Other web and mobile sessions were signed out.');
    }
}
